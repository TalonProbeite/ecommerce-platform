<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Payments;

use App\Application\Exceptions\RetryableException;
use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Application\Payments\StripeEvent;
use App\Application\Payments\Webhooks\OnPaymentSucceeded;
use App\Domain\Events\OrderPaid;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\Support\Fakes\RecordingLogger;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class OnPaymentSucceededTest extends TestCase
{
    use OrderFixtures;

    private CarbonImmutable $now;
    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private InMemoryPaymentRepository $payments;
    private FakePaymentGateway $gateway;
    private RecordingLogger $logger;
    private OnPaymentSucceeded $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        $clock = new FixedClock($this->now);
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, $clock);
        $this->payments = new InMemoryPaymentRepository();
        $this->gateway = new FakePaymentGateway();
        $this->logger = new RecordingLogger();

        $this->useCase = new OnPaymentSucceeded(
            new FakeTransactionRunner(),
            $this->orders,
            $this->payments,
            $this->gateway,
            new SequenceIdGenerator(),
            $this->logger,
        );
    }

    #[Test]
    public function it_marks_a_pending_order_as_paid_and_writes_order_paid(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(PaymentStatus::PROCESSING);

        $this->useCase->handle($this->event(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAID, $order->status());

        $events = $this->outbox->eventsOf(OrderPaid::class);

        self::assertCount(1, $events);
        self::assertSame([
            'order_id' => self::ORDER_ID,
            'store_id' => self::STORE_ID,
            'customer_id' => self::CUSTOMER_ID,
            'amount_total' => 7000,
            'currency' => 'EUR',
            'paid_at' => $this->now->utc()->format('Y-m-d\TH:i:s.uP'),
        ], $events[0]->toArray());

        $payment = $this->payments->payments()[0];

        self::assertSame(PaymentStatus::SUCCEEDED, $payment->status);
        self::assertNotNull($payment->succeededAt);
        self::assertTrue($payment->succeededAt->equalTo($this->now));
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(0, $this->payments->refunds());
    }

    #[Test]
    public function it_refunds_a_cancelled_order_and_does_not_write_order_paid(): void
    {
        $this->seedOrder(OrderStatus::CANCELLED);
        $this->seedPayment(PaymentStatus::PROCESSING);

        $this->useCase->handle($this->event(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::CANCELLED, $order->status());
        self::assertCount(0, $this->outbox->eventsOf(OrderPaid::class));

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame('pi_test_1', $this->gateway->refundCalls[0]['providerPaymentId']);
        self::assertSame(7000, $this->gateway->refundCalls[0]['amount']);
        self::assertSame('order:' . self::ORDER_ID . ':refund:all', $this->gateway->refundCalls[0]['idempotencyKey']);

        $refunds = $this->payments->refunds();

        self::assertCount(1, $refunds);
        self::assertSame('order_cancelled', $refunds[0]->reason);
        self::assertSame(RefundStatus::SUCCEEDED, $refunds[0]->status);
        self::assertSame('re_test_1', $refunds[0]->providerRefundId);
        self::assertSame(7000, $refunds[0]->amount);
    }

    #[Test]
    public function it_does_not_create_a_second_refund_for_a_cancelled_order(): void
    {
        $this->seedOrder(OrderStatus::CANCELLED);
        $this->seedPayment(PaymentStatus::PROCESSING);
        $this->payments->insertRefund(new RefundRecord(
            id: '01930000-0000-7000-8000-0000000000e1',
            orderId: self::ORDER_ID,
            orderItemId: null,
            paymentId: '01930000-0000-7000-8000-0000000000f1',
            providerRefundId: 're_existing',
            amount: 7000,
            reason: 'order_cancelled',
            status: RefundStatus::SUCCEEDED,
            createdAt: $this->now,
            updatedAt: $this->now,
        ));

        $this->useCase->handle($this->event(eventId: 'evt_duplicate_refund'), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::CANCELLED, $order->status());
        self::assertCount(0, $this->outbox->eventsOf(OrderPaid::class));
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(1, $this->payments->refunds());
    }

    #[Test]
    public function it_is_a_no_op_for_an_already_paid_order(): void
    {
        $this->seedOrder(OrderStatus::PAID);
        $this->seedPayment(PaymentStatus::SUCCEEDED);

        $this->useCase->handle($this->event(eventId: 'evt_different'), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAID, $order->status());
        self::assertCount(0, $this->outbox->eventsOf(OrderPaid::class));
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(0, $this->payments->refunds());
    }

    #[Test]
    public function it_does_not_change_anything_when_the_amount_does_not_match(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(PaymentStatus::PROCESSING);

        $this->useCase->handle($this->event(amountReceived: 6999), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());
        self::assertSame(PaymentStatus::PROCESSING, $this->payments->payments()[0]->status);
        self::assertCount(0, $this->outbox->eventsOf(OrderPaid::class));
        self::assertTrue($this->logger->hasLevel('critical'));
    }

    #[Test]
    public function it_retries_when_the_order_status_is_not_allowed(): void
    {
        $this->seedOrder(OrderStatus::CREATED);
        $this->seedPayment(PaymentStatus::PROCESSING);

        $this->expectException(RetryableException::class);

        $this->useCase->handle($this->event(), $this->now);
    }

    private function seedOrder(OrderStatus $status): Order
    {
        $order = $this->makeOrder();

        if ($status !== OrderStatus::CREATED) {
            $order->markStockReserved($this->now);
        }

        if (in_array($status, [OrderStatus::PAYMENT_PENDING, OrderStatus::PAID, OrderStatus::CANCELLED], true)) {
            $order->markPaymentPending($this->now);
        }

        if ($status === OrderStatus::PAID) {
            $order->markPaid($this->now);
        }

        if ($status === OrderStatus::CANCELLED) {
            $order->cancel(CancelReason::CUSTOMER_REQUEST, ActorRole::CUSTOMER, $this->now);
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

    private function event(
        int $amountReceived = 7000,
        string $currency = 'eur',
        string $eventId = 'evt_test_1',
    ): StripeEvent {
        return new StripeEvent(
            eventId: $eventId,
            type: 'payment_intent.succeeded',
            data: [
                'id' => 'pi_test_1',
                'amount_received' => $amountReceived,
                'currency' => $currency,
            ],
        );
    }
}
