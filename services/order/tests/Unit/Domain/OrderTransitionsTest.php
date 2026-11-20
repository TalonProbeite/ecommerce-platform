<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Events\OrderCancelled;
use App\Domain\Events\OrderCreated;
use App\Domain\Events\OrderIssued;
use App\Domain\Events\OrderPaid;
use App\Domain\Events\OrderReturned;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Exceptions\ItemNotInOrder;
use App\Domain\Exceptions\MixedStoreOrder;
use App\Domain\Exceptions\ReturnBeforeRentalStart;
use App\Domain\Money\Money;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\ItemStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderTransitionsTest extends TestCase
{
    use OrderFixtures;

    #[Test]
    public function a_new_order_starts_in_created_with_a_creation_event(): void
    {
        $order = $this->makeOrder();

        self::assertSame(OrderStatus::CREATED, $order->status());
        self::assertSame(1, $order->version());
        self::assertCount(1, $order->pendingEvents());
        self::assertInstanceOf(OrderCreated::class, $order->pendingEvents()[0]);
    }

    #[Test]
    public function pull_events_returns_and_clears_the_buffer(): void
    {
        $order = $this->makeOrder();

        $events = $order->pullEvents();

        self::assertCount(1, $events);
        self::assertSame([], $order->pullEvents());
        self::assertSame([], $order->pendingEvents());
    }

    #[Test]
    public function stock_can_be_reserved_only_from_created(): void
    {
        $order = $this->makeOrder();
        $at = $this->at();
        $order->markStockReserved($at);

        self::assertSame(OrderStatus::STOCK_RESERVED, $order->status());

        $this->expectException(InvalidOrderTransition::class);

        $order->markStockReserved($at);
    }

    #[Test]
    public function failed_stock_reservation_cancels_the_order_and_all_items(): void
    {
        $order = $this->makeOrder(items: [
            $this->makeItem('01930000-0000-7000-8000-000000000001'),
            $this->makeItem('01930000-0000-7000-8000-000000000002'),
        ]);
        $order->pullEvents();
        $failedAt = $this->at('2026-11-20T10:05:00.000000+00:00');

        $order->markStockReservationFailed($failedAt);

        self::assertSame(OrderStatus::CANCELLED, $order->status());
        self::assertSame(CancelReason::STOCK_UNAVAILABLE, $order->cancelReason());
        self::assertNotNull($order->cancelledAt());
        self::assertTrue($order->cancelledAt()->equalTo($failedAt));

        foreach ($order->items() as $item) {
            self::assertSame(ItemStatus::CANCELLED, $item->status());
        }

        self::assertSame([], $order->pendingEvents());
    }

    #[Test]
    public function payment_can_be_pending_from_stock_reserved_and_retried(): void
    {
        $order = $this->makeOrder();
        $at = $this->at();
        $order->markStockReserved($at);

        $order->markPaymentPending($at->addMinute());
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());

        $order->markPaymentPending($at->addMinutes(2));
        self::assertSame(OrderStatus::PAYMENT_PENDING, $order->status());
    }

    #[Test]
    public function payment_pending_cannot_be_reached_from_created(): void
    {
        $this->expectException(InvalidOrderTransition::class);

        $this->makeOrder()->markPaymentPending($this->at());
    }

    #[Test]
    public function paid_can_only_be_reached_from_payment_pending(): void
    {
        $order = $this->paymentPendingOrder();
        $order->pullEvents();

        $order->markPaid($this->at('2026-11-20T10:07:00.000000+00:00'));

        self::assertSame(OrderStatus::PAID, $order->status());
        self::assertCount(1, $order->pendingEvents());
        self::assertInstanceOf(OrderPaid::class, $order->pendingEvents()[0]);
    }

    #[Test]
    public function the_paid_event_carries_the_payment_payload(): void
    {
        $order = $this->paymentPendingOrder();
        $order->pullEvents();
        $paidAt = $this->at('2026-11-20T10:07:00.123456+00:00');

        $order->markPaid($paidAt);

        $event = $order->pendingEvents()[0];

        self::assertInstanceOf(OrderPaid::class, $event);
        self::assertSame([
            'order_id' => self::ORDER_ID,
            'store_id' => self::STORE_ID,
            'customer_id' => self::CUSTOMER_ID,
            'amount_total' => 14000,
            'currency' => 'EUR',
            'paid_at' => '2026-11-20T10:07:00.123456+00:00',
        ], $event->toArray());
    }

    #[Test]
    public function paid_cannot_be_reached_from_created(): void
    {
        $this->expectException(InvalidOrderTransition::class);

        $this->makeOrder()->markPaid($this->at());
    }

    #[Test]
    public function cancellation_is_allowed_before_issue(): void
    {
        $preparations = [
            'created' => static fn (Order $order): Order => $order,
            'stock_reserved' => function (Order $order): Order {
                $order->markStockReserved($this->at());

                return $order;
            },
            'payment_pending' => function (Order $order): Order {
                $order->markStockReserved($this->at());
                $order->markPaymentPending($this->at()->addMinute());

                return $order;
            },
            'paid' => function (Order $order): Order {
                $order->markStockReserved($this->at());
                $order->markPaymentPending($this->at()->addMinute());
                $order->markPaid($this->at()->addMinutes(2));

                return $order;
            },
        ];

        foreach ($preparations as $status => $prepare) {
            $order = $prepare($this->makeOrder());
            $order->pullEvents();

            $order->cancel(
                CancelReason::CUSTOMER_REQUEST,
                ActorRole::CUSTOMER,
                $this->at('2026-11-21T09:00:00.000000+00:00'),
            );

            self::assertSame(OrderStatus::CANCELLED, $order->status(), $status);
            self::assertSame(CancelReason::CUSTOMER_REQUEST, $order->cancelReason(), $status);
            self::assertCount(1, $order->pendingEvents(), $status);
            self::assertInstanceOf(OrderCancelled::class, $order->pendingEvents()[0], $status);

            foreach ($order->items() as $item) {
                self::assertSame(ItemStatus::CANCELLED, $item->status(), $status);
            }
        }
    }

    #[Test]
    public function forbidden_transitions_raise_an_invalid_transition_error(): void
    {
        $cases = [
            'reserve twice' => [
                fn (): Order => $this->stockReservedOrder(),
                function (Order $order): void {
                    $order->markStockReserved($this->at());
                },
            ],
            'pay before requesting payment' => [
                fn (): Order => $this->makeOrder(),
                function (Order $order): void {
                    $order->markPaid($this->at());
                },
            ],
            'request payment from paid' => [
                fn (): Order => $this->paidOrder(),
                function (Order $order): void {
                    $order->markPaymentPending($this->at());
                },
            ],
            'issue before payment' => [
                fn (): Order => $this->makeOrder(),
                function (Order $order): void {
                    $order->issueItems(
                        [$order->items()[0]->lineId()],
                        $this->at('2026-11-21T09:00:00.000000+00:00'),
                        'manager-1',
                    );
                },
            ],
            'return before issue' => [
                fn (): Order => $this->paidOrder(),
                function (Order $order): void {
                    $order->returnItems(
                        [$order->items()[0]->lineId()],
                        $this->at('2026-11-21T09:00:00.000000+00:00'),
                        'manager-1',
                    );
                },
            ],
            'issue after completion' => [
                fn (): Order => $this->completedOrder(),
                function (Order $order): void {
                    $order->issueItems(
                        [$order->items()[0]->lineId()],
                        $this->at('2026-11-22T09:00:00.000000+00:00'),
                        'manager-1',
                    );
                },
            ],
            'return after completion' => [
                fn (): Order => $this->completedOrder(),
                function (Order $order): void {
                    $order->returnItems(
                        [$order->items()[0]->lineId()],
                        $this->at('2026-11-22T09:00:00.000000+00:00'),
                        'manager-1',
                    );
                },
            ],
        ];

        foreach ($cases as $name => $case) {
            [$order, $action] = $case;

            try {
                $action($order());
                self::fail(sprintf('Transition "%s" must be forbidden.', $name));
            } catch (InvalidOrderTransition) {
                self::assertTrue(true, $name);
            }
        }
    }

    #[Test]
    public function cancellation_from_in_rental_is_forbidden(): void
    {
        $order = $this->issuedOrder();

        $this->expectException(InvalidOrderTransition::class);

        $order->cancel(
            CancelReason::ADMIN_REQUEST,
            ActorRole::ADMIN,
            $this->at('2026-11-22T09:00:00.000000+00:00'),
        );
    }

    #[Test]
    public function cancellation_from_completed_is_forbidden(): void
    {
        $order = $this->completedOrder();

        $this->expectException(InvalidOrderTransition::class);

        $order->cancel(
            CancelReason::ADMIN_REQUEST,
            ActorRole::ADMIN,
            $this->at('2026-11-23T09:00:00.000000+00:00'),
        );
    }

    #[Test]
    public function first_issue_moves_a_paid_order_to_in_rental(): void
    {
        $order = $this->paidOrder();
        $order->pullEvents();
        $lineIds = $this->lineIds($order);
        $at = $this->at('2026-11-21T09:00:00.000000+00:00');

        $result = $order->issueItems([$lineIds[0]], $at, 'manager-1');

        self::assertSame(OrderStatus::IN_RENTAL, $order->status());
        self::assertSame([$lineIds[0]], $result->processedItemIds);
        self::assertSame([], $result->skippedItemIds);
        self::assertSame(ItemStatus::ISSUED, $order->items()[0]->status());
        self::assertSame(ItemStatus::RESERVED, $order->items()[1]->status());
        self::assertCount(1, $order->pendingEvents());
        self::assertInstanceOf(OrderIssued::class, $order->pendingEvents()[0]);
    }

    #[Test]
    public function issue_from_in_rental_is_allowed_and_is_idempotent(): void
    {
        $order = $this->paidOrder();
        $lineIds = $this->lineIds($order);
        $at = $this->at('2026-11-21T09:00:00.000000+00:00');

        $order->issueItems([$lineIds[0]], $at, 'manager-1');
        $order->pullEvents();

        $result = $order->issueItems([$lineIds[0], $lineIds[1]], $at->addHour(), 'manager-1');

        self::assertSame([$lineIds[1]], $result->processedItemIds);
        self::assertSame([$lineIds[0]], $result->skippedItemIds);
        self::assertSame(OrderStatus::IN_RENTAL, $order->status());
    }

    #[Test]
    public function issue_is_forbidden_before_payment(): void
    {
        $order = $this->makeOrder();
        $lineIds = $this->lineIds($order);

        $this->expectException(InvalidOrderTransition::class);

        $order->issueItems([$lineIds[0]], $this->at(), 'manager-1');
    }

    #[Test]
    public function issuing_a_foreign_item_fails_without_partial_changes(): void
    {
        $order = $this->paidOrder();
        $lineIds = $this->lineIds($order);

        try {
            $order->issueItems([$lineIds[0], '01930000-0000-7000-8000-0000000000ff'], $this->at(), 'manager-1');
            self::fail('A foreign item id must be rejected.');
        } catch (ItemNotInOrder) {
            self::assertSame(OrderStatus::PAID, $order->status());
            self::assertSame(ItemStatus::RESERVED, $order->items()[0]->status());
        }
    }

    #[Test]
    public function return_requires_in_rental(): void
    {
        $order = $this->paidOrder();
        $lineIds = $this->lineIds($order);

        $this->expectException(InvalidOrderTransition::class);

        $order->returnItems([$lineIds[0]], $this->at(), 'manager-1');
    }

    #[Test]
    public function returning_an_item_before_its_rental_starts_is_forbidden(): void
    {
        $order = $this->issuedOrder();
        $lineIds = $this->lineIds($order);

        $this->expectException(ReturnBeforeRentalStart::class);

        $order->returnItems(
            [$lineIds[0]],
            $this->at('2026-11-20T12:00:00.000000+00:00'),
            'manager-1',
        );
    }

    #[Test]
    public function partial_return_keeps_the_order_in_rental_and_returns_the_item_deposit(): void
    {
        $order = $this->issuedOrder();
        $order->pullEvents();
        $lineIds = $this->lineIds($order);
        $at = $this->at('2026-11-21T12:00:00.000000+00:00');

        $result = $order->returnItems([$lineIds[0]], $at, 'manager-1');

        self::assertSame(OrderStatus::IN_RENTAL, $order->status());
        self::assertSame([$lineIds[0]], $result->processedItemIds);
        self::assertTrue($result->depositReturned->equals(Money::fromMinorUnits(5000)));
        self::assertSame(ItemStatus::RETURNED, $order->items()[0]->status());
        self::assertCount(1, $order->pendingEvents());
        self::assertInstanceOf(OrderReturned::class, $order->pendingEvents()[0]);
    }

    #[Test]
    public function returning_the_last_item_completes_the_order(): void
    {
        $order = $this->issuedOrder();
        $order->pullEvents();
        $lineIds = $this->lineIds($order);
        $returnedAt = $this->at('2026-11-21T12:00:00.000000+00:00');

        $order->returnItems([$lineIds[0]], $returnedAt, 'manager-1');
        $result = $order->returnItems([$lineIds[1]], $returnedAt->addMinute(), 'manager-1');

        self::assertSame(OrderStatus::COMPLETED, $order->status());
        self::assertNotNull($order->completedAt());
        self::assertSame([$lineIds[1]], $result->processedItemIds);
        self::assertTrue($result->depositReturned->equals(Money::fromMinorUnits(5000)));
    }

    #[Test]
    public function returning_an_already_returned_item_is_skipped(): void
    {
        $order = $this->issuedOrder();
        $lineIds = $this->lineIds($order);
        $at = $this->at('2026-11-21T12:00:00.000000+00:00');

        $order->returnItems([$lineIds[0]], $at, 'manager-1');
        $order->pullEvents();
        $result = $order->returnItems([$lineIds[0]], $at->addHour(), 'manager-1');

        self::assertSame([], $result->processedItemIds);
        self::assertSame([$lineIds[0]], $result->skippedItemIds);
        self::assertTrue($result->depositReturned->isZero());
        self::assertSame([], $order->pendingEvents());
    }

    #[Test]
    public function an_issued_order_cannot_be_completed_without_returning_every_item(): void
    {
        $order = $this->issuedOrder();

        self::assertSame(OrderStatus::IN_RENTAL, $order->status());
        self::assertNull($order->completedAt());
    }

    #[Test]
    public function an_order_rejects_items_from_different_stores(): void
    {
        $this->expectException(MixedStoreOrder::class);

        $this->makeOrder(items: [
            $this->makeItem('01930000-0000-7000-8000-000000000001'),
            $this->makeItem(
                '01930000-0000-7000-8000-000000000002',
                storeId: '44444444-4444-4444-8444-444444444444',
            ),
        ]);
    }

    private function stockReservedOrder(): Order
    {
        $order = $this->makeOrder();
        $order->markStockReserved($this->at());

        return $order;
    }

    private function paymentPendingOrder(): Order
    {
        $order = $this->makeOrder(items: [
            $this->makeItem('01930000-0000-7000-8000-000000000001'),
            $this->makeItem('01930000-0000-7000-8000-000000000002', productId: '44444444-4444-4444-8444-444444444444'),
        ]);
        $order->markStockReserved($this->at());
        $order->markPaymentPending($this->at()->addMinute());

        return $order;
    }

    private function paidOrder(): Order
    {
        $order = $this->paymentPendingOrder();
        $order->markPaid($this->at()->addMinutes(2));

        return $order;
    }

    private function issuedOrder(): Order
    {
        $order = $this->paidOrder();
        $lineIds = $this->lineIds($order);
        $order->issueItems($lineIds, $this->at('2026-11-21T09:00:00.000000+00:00'), 'manager-1');

        return $order;
    }

    private function completedOrder(): Order
    {
        $order = $this->issuedOrder();
        $order->returnItems(
            $this->lineIds($order),
            CarbonImmutable::parse('2026-11-21T12:00:00.000000+00:00'),
            'manager-1',
        );

        return $order;
    }

    /**
     * @return list<string>
     */
    private function lineIds(Order $order): array
    {
        return array_map(
            static fn ($item): string => $item->lineId(),
            $order->items(),
        );
    }
}
