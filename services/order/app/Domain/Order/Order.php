<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Events\OrderCancelled;
use App\Domain\Events\OrderCreated;
use App\Domain\Events\OrderEvent;
use App\Domain\Events\OrderIssued;
use App\Domain\Events\OrderPaid;
use App\Domain\Events\OrderReturned;
use App\Domain\Events\OrderStatusChanged;
use App\Domain\Exceptions\InvalidItemTransition;
use App\Domain\Exceptions\InvalidOrderItems;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Exceptions\ItemNotInOrder;
use App\Domain\Exceptions\MixedStoreOrder;
use App\Domain\Exceptions\ReturnBeforeRentalStart;
use App\Domain\Money\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Order
{
    /**
     * @var list<OrderItem>
     */
    private array $items;

    /**
     * @var list<OrderEvent>
     */
    private array $events = [];

    /**
     * @var list<OrderStatusChanged>
     */
    private array $statusChanges = [];

    private int $version;

    private int $persistedVersion;

    private OrderStatus $status;

    private ?CancelReason $cancelReason;

    private Money $subtotal;

    private Money $deposit;

    private ?CarbonImmutable $cancelledAt;

    private ?CarbonImmutable $completedAt;

    private CarbonImmutable $updatedAt;

    /**
     * @param list<OrderItem> $items
     */
    private function __construct(
        private readonly string $id,
        private readonly string $customerId,
        private readonly string $storeId,
        private readonly string $idempotencyKey,
        private readonly string $requestFingerprint,
        private readonly CarbonImmutable $createdAt,
        OrderStatus $status,
        ?CancelReason $cancelReason,
        array $items,
        Money $subtotal,
        Money $deposit,
        int $version,
        int $persistedVersion,
        ?CarbonImmutable $cancelledAt,
        ?CarbonImmutable $completedAt,
        CarbonImmutable $updatedAt,
    ) {
        $this->assertItems($items);
        $this->assertSingleStore($storeId, $items);
        $this->status = $status;
        $this->cancelReason = $cancelReason;
        $this->items = $items;
        $this->subtotal = $subtotal;
        $this->deposit = $deposit;
        $this->version = $version;
        $this->persistedVersion = $persistedVersion;
        $this->cancelledAt = $cancelledAt;
        $this->completedAt = $completedAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function create(
        string $id,
        string $customerId,
        string $storeId,
        array $items,
        string $idempotencyKey,
        string $requestFingerprint,
        CarbonImmutable $createdAt,
    ): self {
        $subtotal = Money::zero();
        $deposit = Money::zero();

        foreach ($items as $item) {
            $subtotal = $subtotal->add($item->lineTotal());
            $deposit = $deposit->add($item->depositTotal());
        }

        $order = new self(
            id: $id,
            customerId: $customerId,
            storeId: $storeId,
            idempotencyKey: $idempotencyKey,
            requestFingerprint: $requestFingerprint,
            createdAt: $createdAt,
            status: OrderStatus::CREATED,
            cancelReason: null,
            items: $items,
            subtotal: $subtotal,
            deposit: $deposit,
            version: 1,
            persistedVersion: 0,
            cancelledAt: null,
            completedAt: null,
            updatedAt: $createdAt,
        );

        $order->events[] = new OrderCreated(
            orderId: $order->id,
            storeId: $order->storeId,
            items: $order->eventItemsFull(),
            customerId: $order->customerId,
        );

        $order->statusChanges[] = new OrderStatusChanged(
            orderId: $order->id,
            fromStatus: null,
            toStatus: OrderStatus::CREATED,
            actorId: $order->customerId,
            actorRole: ActorRole::CUSTOMER,
            at: $order->createdAt,
            itemIds: $order->allItemIds(),
        );

        return $order;
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function restore(
        string $id,
        string $customerId,
        string $storeId,
        OrderStatus $status,
        ?CancelReason $cancelReason,
        array $items,
        Money $subtotal,
        Money $deposit,
        int $version,
        string $idempotencyKey,
        string $requestFingerprint,
        CarbonImmutable $createdAt,
        CarbonImmutable $updatedAt,
        ?CarbonImmutable $cancelledAt = null,
        ?CarbonImmutable $completedAt = null,
    ): self {
        return new self(
            id: $id,
            customerId: $customerId,
            storeId: $storeId,
            idempotencyKey: $idempotencyKey,
            requestFingerprint: $requestFingerprint,
            createdAt: $createdAt,
            status: $status,
            cancelReason: $cancelReason,
            items: $items,
            subtotal: $subtotal,
            deposit: $deposit,
            version: $version,
            persistedVersion: $version,
            cancelledAt: $cancelledAt,
            completedAt: $completedAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function storeId(): string
    {
        return $this->storeId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function cancelReason(): ?CancelReason
    {
        return $this->cancelReason;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function deposit(): Money
    {
        return $this->deposit;
    }

    public function total(): Money
    {
        return $this->subtotal->add($this->deposit);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function persistedVersion(): int
    {
        return $this->persistedVersion;
    }

    public function markPersisted(): void
    {
        $this->persistedVersion = $this->version;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function requestFingerprint(): string
    {
        return $this->requestFingerprint;
    }

    public function createdAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    public function cancelledAt(): ?CarbonImmutable
    {
        return $this->cancelledAt;
    }

    public function completedAt(): ?CarbonImmutable
    {
        return $this->completedAt;
    }

    public function markStockReserved(CarbonImmutable $at): void
    {
        $this->assertOrderStatus([OrderStatus::CREATED], OrderStatus::STOCK_RESERVED);
        $fromStatus = $this->status;

        $this->status = OrderStatus::STOCK_RESERVED;
        $this->updatedAt = $at;
        $this->version++;

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: OrderStatus::STOCK_RESERVED,
            actorId: null,
            actorRole: ActorRole::SYSTEM,
            at: $at,
        );
    }

    public function markStockReservationFailed(CarbonImmutable $at): void
    {
        $this->assertOrderStatus([OrderStatus::CREATED], OrderStatus::CANCELLED);
        $fromStatus = $this->status;

        $this->status = OrderStatus::CANCELLED;
        $this->cancelReason = CancelReason::STOCK_UNAVAILABLE;
        $this->cancelledAt = $at;
        $this->updatedAt = $at;
        $this->cancelAllItems();
        $this->version++;

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: OrderStatus::CANCELLED,
            actorId: null,
            actorRole: ActorRole::SYSTEM,
            at: $at,
            itemIds: $this->allItemIds(),
        );
    }

    public function markPaymentPending(CarbonImmutable $at): void
    {
        $this->assertOrderStatus(
            [OrderStatus::STOCK_RESERVED, OrderStatus::PAYMENT_PENDING],
            OrderStatus::PAYMENT_PENDING,
        );
        $fromStatus = $this->status;

        $this->status = OrderStatus::PAYMENT_PENDING;
        $this->updatedAt = $at;
        $this->version++;

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: OrderStatus::PAYMENT_PENDING,
            actorId: null,
            actorRole: ActorRole::CUSTOMER,
            at: $at,
        );
    }

    public function markPaid(CarbonImmutable $at): void
    {
        $this->assertOrderStatus([OrderStatus::PAYMENT_PENDING], OrderStatus::PAID);
        $fromStatus = $this->status;

        $this->status = OrderStatus::PAID;
        $this->updatedAt = $at;
        $this->version++;

        $this->events[] = new OrderPaid(
            orderId: $this->id,
            storeId: $this->storeId,
            customerId: $this->customerId,
            amountTotal: $this->total()->amount(),
            paidAt: $at,
        );

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: OrderStatus::PAID,
            actorId: null,
            actorRole: ActorRole::STRIPE,
            at: $at,
        );
    }

    public function cancel(CancelReason $reason, ActorRole $actor, CarbonImmutable $at): void
    {
        $this->assertOrderStatus([
            OrderStatus::CREATED,
            OrderStatus::STOCK_RESERVED,
            OrderStatus::PAYMENT_PENDING,
            OrderStatus::PAID,
        ], OrderStatus::CANCELLED);

        $fromStatus = $this->status;
        $this->status = OrderStatus::CANCELLED;
        $this->cancelReason = $reason;
        $this->cancelledAt = $at;
        $this->updatedAt = $at;
        $this->cancelAllItems();
        $this->version++;

        $this->events[] = new OrderCancelled(
            orderId: $this->id,
            storeId: $this->storeId,
            items: $this->eventItemsShort(),
            fromStatus: $fromStatus,
            actorRole: $actor,
            customerId: $this->customerId,
        );

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: OrderStatus::CANCELLED,
            actorId: null,
            actorRole: $actor,
            at: $at,
            itemIds: $this->allItemIds(),
        );
    }

    /**
     * @param list<string> $itemIds
     */
    public function issueItems(
        array $itemIds,
        CarbonImmutable $at,
        string $actorId,
    ): ItemOperationResult {
        $this->assertOrderStatus([OrderStatus::PAID, OrderStatus::IN_RENTAL], OrderStatus::IN_RENTAL);

        if ($itemIds === []) {
            throw new InvalidArgumentException('At least one order item id is required.');
        }

        $items = $this->indexItemsById($itemIds);
        $this->assertItemsCanBeIssued($items, $itemIds);
        $processed = [];
        $skipped = [];

        foreach ($itemIds as $itemId) {
            $item = $items[$itemId];

            if ($item->isIssued()) {
                $skipped[] = $itemId;

                continue;
            }

            $item->issue($actorId, $at);
            $processed[] = $itemId;
        }

        if ($processed === []) {
            return new ItemOperationResult($processed, $skipped, Money::zero());
        }

        $fromStatus = $this->status;

        if ($this->status === OrderStatus::PAID) {
            $this->status = OrderStatus::IN_RENTAL;
        }

        $this->updatedAt = $at;
        $this->version++;

        $this->events[] = new OrderIssued(
            orderId: $this->id,
            storeId: $this->storeId,
            items: $this->eventItemsFor($processed),
            fromStatus: $fromStatus,
            toStatus: $this->status,
            actorId: $actorId,
        );

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: $this->status,
            actorId: $actorId,
            actorRole: ActorRole::STORE_MANAGER,
            at: $at,
            itemIds: $processed,
        );

        return new ItemOperationResult($processed, $skipped, Money::zero());
    }

    /**
     * @param list<string> $itemIds
     */
    public function returnItems(
        array $itemIds,
        CarbonImmutable $at,
        string $actorId,
    ): ItemOperationResult {
        $this->assertOrderStatus([OrderStatus::IN_RENTAL], OrderStatus::IN_RENTAL);

        if ($itemIds === []) {
            throw new InvalidArgumentException('At least one order item id is required.');
        }

        $items = $this->indexItemsById($itemIds);
        $this->assertItemsCanBeReturned($items, $itemIds, $at);
        $processed = [];
        $skipped = [];
        $depositReturned = Money::zero();

        foreach ($itemIds as $itemId) {
            $item = $items[$itemId];

            if ($item->isReturned()) {
                $skipped[] = $itemId;

                continue;
            }

            $item->return($actorId, $at);
            $depositReturned = $depositReturned->add($item->depositTotal());
            $processed[] = $itemId;
        }

        if ($processed === []) {
            return new ItemOperationResult($processed, $skipped, $depositReturned);
        }

        $fromStatus = $this->status;
        $this->status = $this->allItemsReturned()
            ? OrderStatus::COMPLETED
            : OrderStatus::IN_RENTAL;

        if ($this->status === OrderStatus::COMPLETED) {
            $this->completedAt = $at;
        }

        $this->updatedAt = $at;
        $this->version++;

        $this->events[] = new OrderReturned(
            orderId: $this->id,
            storeId: $this->storeId,
            items: $this->eventItemsFor($processed),
            fromStatus: $fromStatus,
            toStatus: $this->status,
            actorId: $actorId,
        );

        $this->statusChanges[] = new OrderStatusChanged(
            orderId: $this->id,
            fromStatus: $fromStatus,
            toStatus: $this->status,
            actorId: $actorId,
            actorRole: ActorRole::STORE_MANAGER,
            at: $at,
            itemIds: $processed,
        );

        return new ItemOperationResult($processed, $skipped, $depositReturned);
    }

    /**
     * @return list<OrderEvent>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * @return list<OrderEvent>
     */
    public function pendingEvents(): array
    {
        return $this->events;
    }

    /**
     * @return list<OrderStatusChanged>
     */
    public function pullStatusChanges(): array
    {
        $changes = $this->statusChanges;
        $this->statusChanges = [];

        return $changes;
    }

    /**
     * @return list<OrderStatusChanged>
     */
    public function pendingStatusChanges(): array
    {
        return $this->statusChanges;
    }

    public function copy(): self
    {
        return clone $this;
    }

    public function __clone(): void
    {
        $this->items = array_map(
            static fn (OrderItem $item): OrderItem => clone $item,
            $this->items,
        );
        $this->events = [];
        $this->statusChanges = [];
    }

    /**
     * @param list<OrderStatus> $allowed
     */
    private function assertOrderStatus(array $allowed, OrderStatus $requested): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw new InvalidOrderTransition($this->status, $requested);
        }
    }

    /**
     * @param list<string> $itemIds
     * @return array<string, OrderItem>
     */
    private function indexItemsById(array $itemIds): array
    {
        $indexed = [];

        foreach ($this->items as $item) {
            $indexed[$item->lineId()] = $item;
        }

        foreach ($itemIds as $itemId) {
            if (! isset($indexed[$itemId])) {
                throw new ItemNotInOrder($this->id, $itemId);
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, OrderItem> $items
     * @param list<string> $itemIds
     */
    private function assertItemsCanBeIssued(array $items, array $itemIds): void
    {
        foreach (array_unique($itemIds) as $itemId) {
            $item = $items[$itemId];

            if ($item->isIssued()) {
                continue;
            }

            if (! $item->isReserved()) {
                throw new InvalidItemTransition($itemId, $item->status(), ItemStatus::ISSUED);
            }
        }
    }

    /**
     * @param array<string, OrderItem> $items
     * @param list<string> $itemIds
     */
    private function assertItemsCanBeReturned(array $items, array $itemIds, CarbonImmutable $at): void
    {
        foreach (array_unique($itemIds) as $itemId) {
            $item = $items[$itemId];

            if ($item->isReturned()) {
                continue;
            }

            if (! $item->isIssued()) {
                throw new InvalidItemTransition($itemId, $item->status(), ItemStatus::RETURNED);
            }

            if ($at->lessThan($item->rentalPeriod()->start())) {
                throw new ReturnBeforeRentalStart($itemId, $item->rentalPeriod()->start());
            }
        }
    }

    private function cancelAllItems(): void
    {
        foreach ($this->items as $item) {
            $item->cancel();
        }
    }

    private function allItemsReturned(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->isReturned()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<OrderItem> $items
     */
    private function assertItems(array $items): void
    {
        if ($items === []) {
            throw InvalidOrderItems::empty();
        }

        if (count($items) > 100) {
            throw InvalidOrderItems::tooMany(count($items));
        }
    }

    /**
     * @param list<OrderItem> $items
     */
    private function assertSingleStore(string $storeId, array $items): void
    {
        $storeIds = [$storeId];

        foreach ($items as $item) {
            if ($item->storeId() !== $storeId) {
                $storeIds[] = $item->storeId();
            }
        }

        if (count(array_unique($storeIds)) > 1) {
            throw MixedStoreOrder::forOrder($storeId, $storeIds);
        }
    }

    /**
     * @return list<string>
     */
    private function allItemIds(): array
    {
        return array_map(
            static fn (OrderItem $item): string => $item->lineId(),
            $this->items,
        );
    }

    /**
     * @return list<array{line_id: string, product_id: string, quantity: int, rental_start_at: string, rental_end_at: string}>
     */
    private function eventItemsFull(): array
    {
        return array_map(
            fn (OrderItem $item): array => [
                'line_id' => $item->lineId(),
                'product_id' => $item->productId(),
                'quantity' => $item->quantity(),
                'rental_start_at' => $item->rentalPeriod()->start()->utc()->format('Y-m-d\TH:i:s.uP'),
                'rental_end_at' => $item->rentalPeriod()->end()->utc()->format('Y-m-d\TH:i:s.uP'),
            ],
            $this->items,
        );
    }

    /**
     * @return list<array{line_id: string, product_id: string, quantity: int}>
     */
    private function eventItemsShort(): array
    {
        return $this->eventItemsFor(array_map(
            static fn (OrderItem $item): string => $item->lineId(),
            $this->items,
        ));
    }

    /**
     * @param list<string> $itemIds
     * @return list<array{line_id: string, product_id: string, quantity: int}>
     */
    private function eventItemsFor(array $itemIds): array
    {
        $indexed = [];

        foreach ($this->items as $item) {
            $indexed[$item->lineId()] = $item;
        }

        $result = [];

        foreach ($itemIds as $itemId) {
            $item = $indexed[$itemId];
            $result[] = [
                'line_id' => $item->lineId(),
                'product_id' => $item->productId(),
                'quantity' => $item->quantity(),
            ];
        }

        return $result;
    }
}
