<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Application\Exceptions\OptimisticLockException;
use App\Application\Ports\OrderRepository;
use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

abstract class OrderRepositoryContractTest extends PersistenceTestCase
{
    private const string ORDER_ID = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e';
    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    abstract protected function repository(): OrderRepository;

    abstract protected function assertOutboxContains(string $routingKey, string $orderId): void;

    /**
     * @param list<array{0: ?string, 1: string}> $expected
     */
    abstract protected function assertJournal(string $orderId, array $expected): void;

    #[Test]
    public function an_order_is_saved_and_read_back_with_its_items(): void
    {
        $order = $this->contractOrder();
        $lineIds = $this->lineIds($order);

        $this->repository()->save($order);

        $loaded = $this->repository()->findById($order->id());

        self::assertNotNull($loaded);
        self::assertSame($order->id(), $loaded->id());
        self::assertSame(self::CUSTOMER_ID, $loaded->customerId());
        self::assertSame(self::STORE_ID, $loaded->storeId());
        self::assertSame(OrderStatus::CREATED, $loaded->status());
        self::assertSame(1, $loaded->version());
        self::assertSame(4000, $loaded->subtotal()->amount());
        self::assertSame(10000, $loaded->deposit()->amount());
        self::assertSame($lineIds, $this->lineIds($loaded));
    }

    #[Test]
    public function an_order_can_be_found_by_customer_and_idempotency_key(): void
    {
        $order = $this->contractOrder();

        $this->repository()->save($order);

        $loaded = $this->repository()->findByIdempotencyKey(
            self::CUSTOMER_ID,
            $order->idempotencyKey(),
        );

        self::assertNotNull($loaded);
        self::assertSame($order->id(), $loaded->id());
    }

    #[Test]
    public function items_are_read_back_in_their_original_order(): void
    {
        $order = $this->contractOrder(lineIds: [
            '01930000-0000-7000-8000-000000000001',
            '01930000-0000-7000-8000-000000000002',
            '01930000-0000-7000-8000-000000000003',
        ]);

        $this->repository()->save($order);
        $loaded = $this->repository()->findById($order->id());

        self::assertNotNull($loaded);
        self::assertSame($this->lineIds($order), $this->lineIds($loaded));
    }

    #[Test]
    public function saving_an_outdated_aggregate_raises_an_optimistic_lock_exception(): void
    {
        $orderId = self::ORDER_ID;
        $order = $this->contractOrder($orderId);

        $this->repository()->save($order);

        $first = $this->repository()->findById($orderId);
        $stale = $this->repository()->findById($orderId);

        self::assertNotNull($first);
        self::assertNotNull($stale);

        $first->markStockReserved();
        $stale->markStockReserved();

        $this->repository()->save($first);

        $this->expectException(OptimisticLockException::class);

        $this->repository()->save($stale);
    }

    #[Test]
    public function every_order_status_transition_is_written_to_the_journal(): void
    {
        $order = $this->contractOrder();
        $this->repository()->save($order);

        $loaded = $this->repository()->findById($order->id());
        self::assertNotNull($loaded);

        $loaded->markStockReserved(CarbonImmutable::parse('2026-11-20T10:05:00.000000+00:00'));
        $loaded->markPaymentPending(CarbonImmutable::parse('2026-11-20T10:06:00.000000+00:00'));
        $loaded->markPaid(CarbonImmutable::parse('2026-11-20T10:07:00.000000+00:00'));
        $this->repository()->save($loaded);

        $this->assertJournal($order->id(), [
            [null, 'created'],
            ['created', 'stock_reserved'],
            ['stock_reserved', 'payment_pending'],
            ['payment_pending', 'paid'],
        ]);
    }

    #[Test]
    public function domain_events_are_written_to_the_outbox(): void
    {
        $order = $this->contractOrder();
        $this->repository()->save($order);

        $this->assertOutboxContains('order.created', $order->id());

        $order->markStockReserved();
        $order->markPaymentPending();
        $order->markPaid();
        $this->repository()->save($order);

        $this->assertOutboxContains('order.paid', $order->id());

        $loaded = $this->repository()->findById($order->id());

        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::PAID, $loaded->status());
    }

    /**
     * @param list<string>|null $lineIds
     */
    private function contractOrder(
        string $orderId = self::ORDER_ID,
        ?array $lineIds = null,
    ): Order {
        $lineIds ??= [
            '01930000-0000-7000-8000-000000000001',
            '01930000-0000-7000-8000-000000000002',
        ];

        $items = [];

        foreach ($lineIds as $index => $lineId) {
            $items[] = OrderItem::create(
                lineId: $lineId,
                storeId: self::STORE_ID,
                productId: sprintf('3333333%d-3333-4333-8333-333333333333', $index + 1),
                productName: 'Drill ' . ($index + 1),
                quantity: 1,
                rentalPeriod: new RentalPeriod(
                    CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
                    CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
                ),
                pricePerHour: Money::fromMinorUnits(1000),
                depositPerUnit: Money::fromMinorUnits(5000),
            );
        }

        return Order::create(
            id: $orderId,
            customerId: self::CUSTOMER_ID,
            storeId: self::STORE_ID,
            items: $items,
            idempotencyKey: 'idempotency-' . $orderId,
            requestFingerprint: hash('sha256', 'fingerprint-' . $orderId),
            createdAt: CarbonImmutable::parse('2026-11-20T10:00:00.123456+00:00'),
        );
    }

    /**
     * @return list<string>
     */
    private function lineIds(Order $order): array
    {
        return array_map(
            static fn (OrderItem $item): string => $item->lineId(),
            $order->items(),
        );
    }
}
