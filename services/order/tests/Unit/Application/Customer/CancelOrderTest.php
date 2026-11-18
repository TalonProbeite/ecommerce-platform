<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Customer;

use App\Application\Customer\CancelOrder\CancelOrder;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\PaymentInProgress;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\ProviderPaymentIntent;
use App\Application\Payments\RefundStatus;
use App\Domain\Events\OrderCancelled;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Order\CancelReason;
use App\Domain\Order\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Unit\Application\OrderApplicationTestCase;

final class CancelOrderTest extends OrderApplicationTestCase
{
    private CancelOrder $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new CancelOrder(
            new FakeTransactionRunner(),
            $this->orders,
            $this->payments,
            $this->gateway,
            $this->clock,
            $this->ids,
        );
    }

    #[Test]
    public function it_cancels_a_created_order_without_a_payment(): void
    {
        $this->seedOrder(OrderStatus::CREATED);

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertTrue($result->changed);
        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertSame(CancelReason::CUSTOMER_REQUEST, $result->order->cancelReason());
        self::assertCount(0, $this->gateway->cancelCalls);
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(1, $this->outbox->eventsOf(OrderCancelled::class));
    }

    #[Test]
    public function it_cancels_a_stock_reserved_order_without_a_payment(): void
    {
        $this->seedOrder(OrderStatus::STOCK_RESERVED);

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertCount(0, $this->gateway->cancelCalls);
        self::assertCount(1, $this->outbox->eventsOf(OrderCancelled::class));
    }

    #[Test]
    public function it_cancels_an_open_payment_intent_first(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $payment = $this->seedPayment(self::ORDER_ID, PaymentStatus::REQUIRES_ACTION);

        $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertCount(1, $this->gateway->cancelCalls);
        self::assertSame('pi_test_1', $this->gateway->cancelCalls[0]['providerId']);
        self::assertSame(
            'order:' . self::ORDER_ID . ':cancel-pi:pi_test_1',
            $this->gateway->cancelCalls[0]['idempotencyKey'],
        );
        self::assertSame(PaymentStatus::CANCELED, $this->payments->payments()[0]->status);
        self::assertNotNull($payment);
        self::assertCount(0, $this->gateway->refundCalls);
    }

    #[Test]
    public function it_refunds_the_full_amount_when_cancel_intent_reports_succeeded(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::REQUIRES_ACTION);
        $this->gateway->cancelledIntent = new ProviderPaymentIntent(
            'pi_test_1',
            PaymentStatus::SUCCEEDED,
            null,
        );

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertSame(PaymentStatus::SUCCEEDED, $this->payments->payments()[0]->status);
        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(7000, $this->gateway->refundCalls[0]['amount']);
        self::assertSame(
            'order:' . self::ORDER_ID . ':refund:all',
            $this->gateway->refundCalls[0]['idempotencyKey'],
        );

        $refunds = $this->payments->refunds();

        self::assertCount(1, $refunds);
        self::assertSame('order_cancelled', $refunds[0]->reason);
        self::assertSame(RefundStatus::SUCCEEDED, $refunds[0]->status);
        self::assertSame(7000, $refunds[0]->amount);
    }

    #[Test]
    public function it_skips_a_paid_order_when_only_if_unpaid_is_requested(): void
    {
        $this->seedOrder(OrderStatus::PAID);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED);

        $result = $this->useCase->executeAsSystem(
            self::ORDER_ID,
            CancelReason::PAYMENT_TIMEOUT,
            onlyIfUnpaid: true,
        );

        self::assertFalse($result->changed);
        self::assertSame(OrderStatus::PAID, $result->order->status());
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(0, $this->payments->refunds());
        self::assertCount(0, $this->outbox->eventsOf(OrderCancelled::class));
    }

    #[Test]
    public function it_cancels_and_refunds_a_paid_order_when_only_if_unpaid_is_false(): void
    {
        $this->seedOrder(OrderStatus::PAID);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED);

        $result = $this->useCase->executeAsSystem(self::ORDER_ID, CancelReason::PAYMENT_TIMEOUT);

        self::assertTrue($result->changed);
        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(7000, $this->gateway->refundCalls[0]['amount']);
        self::assertCount(1, $this->payments->refunds());
        self::assertCount(1, $this->outbox->eventsOf(OrderCancelled::class));
    }

    #[Test]
    public function it_returns_conflict_without_changes_when_the_payment_is_processing(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::REQUIRES_ACTION);
        $this->gateway->cancelledIntent = new ProviderPaymentIntent(
            'pi_test_1',
            PaymentStatus::PROCESSING,
            null,
        );

        try {
            $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);
            self::fail('Expected a PaymentInProgress exception.');
        } catch (PaymentInProgress) {
        }

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());
        self::assertSame(PaymentStatus::REQUIRES_ACTION, $this->payments->payments()[0]->status);
        self::assertCount(0, $this->payments->refunds());
        self::assertCount(0, $this->outbox->events());
    }

    #[Test]
    public function it_is_a_no_op_for_an_already_cancelled_order(): void
    {
        $this->seedOrder(OrderStatus::CANCELLED);

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertFalse($result->changed);
        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertCount(0, $this->outbox->events());
        self::assertCount(0, $this->gateway->refundCalls);
    }

    #[Test]
    public function it_rejects_cancellation_of_an_in_rental_order(): void
    {
        $this->seedOrder(OrderStatus::IN_RENTAL);

        $this->expectException(InvalidOrderTransition::class);

        $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);
    }

    #[Test]
    public function it_hides_an_order_from_a_foreign_customer(): void
    {
        $this->seedOrder(OrderStatus::CREATED);

        $this->expectException(OrderNotFound::class);

        $this->useCase->execute(
            $this->customer('11111111-1111-4111-8111-999999999999'),
            self::ORDER_ID,
            CancelReason::CUSTOMER_REQUEST,
        );
    }

    #[Test]
    public function it_rolls_back_when_stripe_is_unavailable(): void
    {
        $this->seedOrder(OrderStatus::PAYMENT_PENDING);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::REQUIRES_ACTION);
        $this->gateway->cancelFailure = new PaymentProviderUnavailable('network error');

        try {
            $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);
            self::fail('Expected a PaymentProviderUnavailable exception.');
        } catch (PaymentProviderUnavailable) {
        }

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());
        self::assertSame(PaymentStatus::REQUIRES_ACTION, $this->payments->payments()[0]->status);
        self::assertCount(0, $this->outbox->events());
    }

    #[Test]
    public function it_writes_order_cancelled_with_the_contract_payload(): void
    {
        $this->seedOrder(OrderStatus::STOCK_RESERVED);

        $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        $events = $this->outbox->eventsOf(OrderCancelled::class);

        self::assertCount(1, $events);
        self::assertSame([
            'order_id' => self::ORDER_ID,
            'store_id' => self::STORE_ID,
            'items' => [[
                'line_id' => '01930000-0000-7000-8000-000000000001',
                'product_id' => '33333333-3333-4333-8333-333333333333',
                'quantity' => 1,
            ]],
        ], $events[0]->toArray());
    }

    #[Test]
    public function it_does_not_create_a_second_refund_when_a_refund_already_exists(): void
    {
        $this->seedOrder(OrderStatus::PAID);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED);
        $this->seedRefund(self::ORDER_ID, 'order_cancelled', RefundStatus::SUCCEEDED, 7000);

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertTrue($result->changed);
        self::assertSame(OrderStatus::CANCELLED, $result->order->status());
        self::assertCount(0, $this->gateway->refundCalls);
        self::assertCount(1, $this->payments->refunds());
    }

    #[Test]
    public function it_does_not_create_a_second_refund_on_repeated_cancellation(): void
    {
        $this->seedOrder(OrderStatus::PAID);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED);

        $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);
        self::assertCount(1, $this->gateway->refundCalls);
        self::assertCount(1, $this->payments->refunds());

        $result = $this->useCase->execute($this->customer(), self::ORDER_ID, CancelReason::CUSTOMER_REQUEST);

        self::assertFalse($result->changed);
        self::assertCount(1, $this->gateway->refundCalls);
        self::assertCount(1, $this->payments->refunds());
        self::assertCount(1, $this->outbox->eventsOf(OrderCancelled::class));
    }
}
