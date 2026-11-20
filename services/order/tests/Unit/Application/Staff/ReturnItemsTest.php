<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Staff;

use App\Application\Exceptions\RefundExceedsPayment;
use App\Application\Payments\PaymentStatus;
use App\Application\Staff\ReturnItems\ReturnItems;
use App\Domain\Events\OrderReturned;
use App\Domain\Exceptions\InvalidItemTransition;
use App\Domain\Order\ItemStatus;
use App\Domain\Order\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Unit\Application\OrderApplicationTestCase;

final class ReturnItemsTest extends OrderApplicationTestCase
{
    private const string ITEM_ONE = '01930000-0000-7000-8000-000000000201';
    private const string ITEM_TWO = '01930000-0000-7000-8000-000000000202';

    private ReturnItems $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new ReturnItems(
            new FakeTransactionRunner(),
            $this->orders,
            $this->payments,
            $this->gateway,
            $this->clock,
            $this->ids,
        );
    }

    #[Test]
    public function it_returns_an_issued_item_and_refunds_its_deposit(): void
    {
        $this->seedOrder(OrderStatus::IN_RENTAL, [$this->makeItem(self::ITEM_ONE)]);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED);

        $result = $this->useCase->execute(
            $this->manager(),
            self::ORDER_ID,
            [self::ITEM_ONE],
        );

        self::assertSame([self::ITEM_ONE], $result->processedItemIds);
        self::assertSame([], $result->skippedItemIds);
        self::assertSame(OrderStatus::COMPLETED, $result->order->status());
        self::assertNotNull($result->order->completedAt());
        self::assertSame(ItemStatus::RETURNED, $result->order->items()[0]->status());

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(5000, $this->gateway->refundCalls[0]['amount']);
        self::assertSame(
            'order:' . self::ORDER_ID . ':refund:item:' . self::ITEM_ONE,
            $this->gateway->refundCalls[0]['idempotencyKey'],
        );

        $refunds = $this->payments->refunds();

        self::assertCount(1, $refunds);
        self::assertSame('item_returned', $refunds[0]->reason);
        self::assertSame(self::ITEM_ONE, $refunds[0]->orderItemId);
        self::assertSame(5000, $refunds[0]->amount);
        self::assertSame(PaymentStatus::SUCCEEDED, $refunds[0]->status);

        $events = $this->outbox->eventsOf(OrderReturned::class);

        self::assertCount(1, $events);
        self::assertSame([[
            'line_id' => self::ITEM_ONE,
            'product_id' => '33333333-3333-4333-8333-333333333333',
            'quantity' => 1,
        ]], $events[0]->items());
    }

    #[Test]
    public function it_completes_the_order_after_the_last_item_is_returned(): void
    {
        $this->seedOrder(OrderStatus::IN_RENTAL, [
            $this->makeItem(self::ITEM_ONE),
            $this->makeItem(self::ITEM_TWO),
        ]);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED, 14000);

        $first = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);

        self::assertSame(OrderStatus::IN_RENTAL, $first->order->status());
        self::assertNull($first->order->completedAt());

        $second = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_TWO]);

        self::assertSame(OrderStatus::COMPLETED, $second->order->status());
        self::assertNotNull($second->order->completedAt());
        self::assertCount(2, $this->payments->refunds());
    }

    #[Test]
    public function it_rejects_returning_a_reserved_item(): void
    {
        $order = $this->makeOrder(null, [
            $this->makeItem(self::ITEM_ONE),
            $this->makeItem(self::ITEM_TWO),
        ]);
        $order->markStockReserved($this->now);
        $order->markPaymentPending($this->now);
        $order->markPaid($this->now);
        $order->issueItems([self::ITEM_ONE], $this->now, $this->manager()->id);
        $this->orders->save($order);
        $this->outbox->reset();

        $this->expectException(InvalidItemTransition::class);

        $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_TWO]);
    }

    #[Test]
    public function it_rejects_a_refund_that_exceeds_succeeded_payments(): void
    {
        $this->seedOrder(OrderStatus::IN_RENTAL, [$this->makeItem(self::ITEM_ONE)]);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED, 3000);

        try {
            $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);
            self::fail('Expected a RefundExceedsPayment exception.');
        } catch (RefundExceedsPayment) {
        }

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::IN_RENTAL, $order->status());
        self::assertSame(ItemStatus::ISSUED, $order->items()[0]->status());
        self::assertCount(0, $this->payments->refunds());
        self::assertCount(0, $this->gateway->refundCalls);
    }

    #[Test]
    public function it_is_a_no_op_when_all_requested_items_are_already_returned(): void
    {
        $this->seedOrder(OrderStatus::IN_RENTAL, [
            $this->makeItem(self::ITEM_ONE),
            $this->makeItem(self::ITEM_TWO),
        ]);
        $this->seedPayment(self::ORDER_ID, PaymentStatus::SUCCEEDED, 14000);

        $first = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);
        $version = $first->order->version();
        $this->outbox->reset();

        $second = $this->useCase->execute($this->manager(), self::ORDER_ID, [self::ITEM_ONE]);

        self::assertSame([], $second->processedItemIds);
        self::assertSame([self::ITEM_ONE], $second->skippedItemIds);
        self::assertSame($version, $second->order->version());
        self::assertCount(0, $this->outbox->eventsOf(OrderReturned::class));
        self::assertCount(1, $this->payments->refunds());
    }
}
