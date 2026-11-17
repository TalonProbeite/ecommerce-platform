<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Payments;

use App\Application\Exceptions\RetryableException;
use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Payments\Webhooks\HandleStripeEvent;
use App\Application\Payments\Webhooks\OnPaymentFailed;
use App\Application\Payments\Webhooks\OnPaymentSucceeded;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\Support\Fakes\InMemoryStripeEventStore;
use Tests\Support\Fakes\RecordingLogger;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class HandleStripeEventTest extends TestCase
{
    use OrderFixtures;

    private const string EVENT_ID = 'evt_test_1';

    private CarbonImmutable $now;
    private InMemoryStripeEventStore $events;
    private InMemoryOrderRepository $orders;
    private InMemoryPaymentRepository $payments;
    private FakeOutboxWriter $outbox;
    private RecordingLogger $logger;
    private HandleStripeEvent $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        $transactions = new FakeTransactionRunner();
        $clock = new FixedClock($this->now);
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, $clock);
        $this->payments = new InMemoryPaymentRepository();
        $this->events = new InMemoryStripeEventStore();
        $this->logger = new RecordingLogger();
        $gateway = new FakePaymentGateway();

        $onSucceeded = new OnPaymentSucceeded(
            $transactions,
            $this->orders,
            $this->payments,
            $gateway,
            new SequenceIdGenerator(),
            $this->logger,
        );
        $onFailed = new OnPaymentFailed($transactions, $this->payments);

        $this->useCase = new HandleStripeEvent(
            $transactions,
            $this->events,
            $onSucceeded,
            $onFailed,
            $this->payments,
            $this->logger,
        );
    }

    #[Test]
    public function it_ignores_an_already_processed_event_id(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(PaymentStatus::PROCESSING);
        $this->events->markProcessed(self::EVENT_ID, 'payment_intent.succeeded', $this->now);

        $this->useCase->handle($this->succeededEvent(), $this->now);

        self::assertSame(OrderStatus::PAYMENT_PENDING, $this->orders->findById(self::ORDER_ID)?->status());
        self::assertSame(PaymentStatus::PROCESSING, $this->payments->payments()[0]->status);
        self::assertCount(1, $this->events->all());
    }

    #[Test]
    public function it_ignores_an_unknown_event_type(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(PaymentStatus::PROCESSING);

        $this->useCase->handle(new StripeEvent(
            eventId: self::EVENT_ID,
            type: 'charge.succeeded',
            data: ['id' => 'ch_test_1'],
        ), $this->now);

        self::assertSame(OrderStatus::PAYMENT_PENDING, $this->orders->findById(self::ORDER_ID)?->status());
        self::assertSame(PaymentStatus::PROCESSING, $this->payments->payments()[0]->status);
        self::assertTrue($this->events->wasProcessed(self::EVENT_ID));
        self::assertTrue($this->logger->hasLevel('info'));
    }

    #[Test]
    public function it_retries_when_the_payment_is_not_committed_yet(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);

        $this->expectException(RetryableException::class);

        $this->useCase->handle($this->succeededEvent(), $this->now);
    }

    private function seedOrder(OrderStatus $status): void
    {
        $order = $this->makeOrder();
        $order->markStockReserved($this->now);

        if ($status === OrderStatus::PAYMENT_PENDING) {
            $order->markPaymentPending($this->now);
        }

        $this->orders->save($order);
    }

    private function seedPayment(string $status): void
    {
        $this->payments->insert(new PaymentRecord(
            id: '01930000-0000-7000-8000-0000000000f1',
            orderId: self::ORDER_ID,
            provider: 'stripe',
            providerPaymentId: 'pi_test_1',
            amount: 7000,
            currency: 'EUR',
            status: $status,
            failureCode: null,
            succeededAt: null,
            createdAt: $this->now,
            updatedAt: $this->now,
        ));
    }

    private function succeededEvent(): StripeEvent
    {
        return new StripeEvent(
            eventId: self::EVENT_ID,
            type: 'payment_intent.succeeded',
            data: [
                'id' => 'pi_test_1',
                'amount_received' => 7000,
                'currency' => 'eur',
            ],
        );
    }
}
