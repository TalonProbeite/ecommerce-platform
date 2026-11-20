<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Payments;

use App\Application\Customer\RequestPaymentIntent\RequestPaymentIntent;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\ProviderPaymentIntent;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class RequestPaymentIntentUseCaseTest extends TestCase
{
    use OrderFixtures;

    private CarbonImmutable $now;
    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private InMemoryPaymentRepository $payments;
    private FakePaymentGateway $gateway;
    private RequestPaymentIntent $useCase;
    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        $clock = new FixedClock($this->now);
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, $clock);
        $this->payments = new InMemoryPaymentRepository();
        $this->gateway = new FakePaymentGateway();
        $this->user = new AuthUser(self::CUSTOMER_ID, Role::CUSTOMER);

        $this->useCase = new RequestPaymentIntent(
            new FakeTransactionRunner(),
            $this->orders,
            $this->payments,
            $this->gateway,
            $clock,
            new SequenceIdGenerator(),
        );
    }

    #[Test]
    public function it_creates_a_new_intent_with_the_order_total_and_attempt_key(): void
    {
        $this->seedOrder(OrderStatus::STOCK_RESERVED);

        $result = $this->useCase->execute($this->user, self::ORDER_ID);

        self::assertTrue($result->created);
        self::assertSame('cs_test_1', $result->clientSecret);
        self::assertSame('requires_payment_method', $result->status);
        self::assertSame(7000, $result->amountTotal);
        self::assertSame('EUR', $result->currency);

        self::assertCount(1, $this->gateway->createCalls);
        self::assertSame(7000, $this->gateway->createCalls[0]['request']->amount);
        self::assertSame('eur', $this->gateway->createCalls[0]['request']->currency);
        self::assertSame(self::ORDER_ID, $this->gateway->createCalls[0]['request']->orderId);
        self::assertSame('order:' . self::ORDER_ID . ':pi:1', $this->gateway->createCalls[0]['idempotencyKey']);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());

        $payments = $this->payments->payments();

        self::assertCount(1, $payments);
        self::assertSame('stripe', $payments[0]->provider);
        self::assertSame('pi_test_1', $payments[0]->providerPaymentId);
        self::assertSame(7000, $payments[0]->amount);
        self::assertSame('EUR', $payments[0]->currency);
        self::assertSame(PaymentStatus::REQUIRES_PAYMENT_METHOD, $payments[0]->status);
    }

    #[Test]
    public function it_returns_the_open_intent_without_creating_a_new_one(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(PaymentStatus::REQUIRES_ACTION);

        $this->gateway->retrievedIntent = new ProviderPaymentIntent(
            'pi_existing',
            PaymentStatus::REQUIRES_ACTION,
            'cs_existing',
        );

        $result = $this->useCase->execute($this->user, self::ORDER_ID);

        self::assertFalse($result->created);
        self::assertSame('cs_existing', $result->clientSecret);
        self::assertSame(PaymentStatus::REQUIRES_ACTION, $result->status);
        self::assertCount(0, $this->gateway->createCalls);
        self::assertSame(['pi_existing'], $this->gateway->retrieveCalls);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());
        self::assertCount(1, $this->payments->payments());
    }

    #[Test]
    public function it_rejects_an_order_in_a_terminal_status(): void
    {
        $order = $this->seedOrder(OrderStatus::STOCK_RESERVED);
        $order->cancel(CancelReason::CUSTOMER_REQUEST, ActorRole::CUSTOMER, $this->now);
        $this->orders->save($order);
        $this->outbox->reset();

        try {
            $this->useCase->execute($this->user, self::ORDER_ID);

            self::fail('Expected InvalidOrderTransition.');
        } catch (InvalidOrderTransition $exception) {
            self::assertSame(OrderStatus::CANCELLED, $exception->currentStatus);
            self::assertSame(OrderStatus::PAYMENT_PENDING, $exception->requestedStatus);
        }

        self::assertCount(0, $this->gateway->createCalls);
        self::assertCount(0, $this->payments->payments());
    }

    #[Test]
    public function it_hides_orders_of_other_customers(): void
    {
        $this->seedOrder(OrderStatus::STOCK_RESERVED);

        $this->expectException(OrderNotFound::class);

        $this->useCase->execute(
            new AuthUser('11111111-1111-4111-8111-999999999999', Role::CUSTOMER),
            self::ORDER_ID,
        );
    }

    #[Test]
    public function it_does_not_persist_anything_when_stripe_fails(): void
    {
        $this->seedOrder(OrderStatus::STOCK_RESERVED);
        $this->gateway->createFailure = new PaymentProviderUnavailable('Stripe is down.');

        try {
            $this->useCase->execute($this->user, self::ORDER_ID);

            self::fail('Expected PaymentProviderUnavailable.');
        } catch (PaymentProviderUnavailable) {
        }

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::STOCK_RESERVED, $order->status());
        self::assertCount(0, $this->payments->payments());
    }

    private function seedOrder(OrderStatus $status): Order
    {
        $order = $this->makeOrder();
        $order->markStockReserved($this->now);

        if ($status === OrderStatus::PAYMENT_PENDING) {
            $order->markPaymentPending($this->now);
        }

        $this->orders->save($order);
        $this->outbox->reset();

        return $order;
    }

    private function seedPayment(string $status): void
    {
        $this->payments->insert(new PaymentRecord(
            id: '01930000-0000-7000-8000-0000000000f1',
            orderId: self::ORDER_ID,
            provider: 'stripe',
            providerPaymentId: 'pi_existing',
            amount: 7000,
            currency: 'EUR',
            status: $status,
            failureCode: null,
            succeededAt: null,
            createdAt: $this->now,
            updatedAt: $this->now,
        ));
    }
}
