<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Exceptions\OptimisticLockException;
use App\Application\Ports\Clock;
use App\Application\Ports\OrderRepository;
use App\Domain\Events\OrderStatusChanged;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Outbox\OutboxWriterInterface;
use Carbon\CarbonImmutable;

final class InMemoryOrderRepository implements OrderRepository
{
    /**
     * @var array<string, Order>
     */
    private array $orders = [];

    /**
     * @var array<string, string>
     */
    private array $idempotencyIndex = [];

    /**
     * @var list<OrderStatusChanged>
     */
    private array $journal = [];

    public function __construct(
        private readonly OutboxWriterInterface $outbox,
        private readonly Clock $clock,
    ) {
    }

    public function findById(string $id): ?Order
    {
        return isset($this->orders[$id]) ? $this->orders[$id]->copy() : null;
    }

    public function findByIdForUpdate(string $id): ?Order
    {
        return $this->findById($id);
    }

    /**
     * @return list<Order>
     */
    public function lockExpiredUnpaidForUpdate(CarbonImmutable $createdBefore, int $limit): array
    {
        $orders = array_values(array_filter(
            $this->orders,
            static fn (Order $order): bool => in_array(
                $order->status(),
                [OrderStatus::STOCK_RESERVED, OrderStatus::PAYMENT_PENDING],
                true,
            ) && $order->createdAt()->lessThan($createdBefore),
        ));

        usort(
            $orders,
            static fn (Order $left, Order $right): int => [$left->createdAt()->getTimestamp(), $left->id()]
                <=> [$right->createdAt()->getTimestamp(), $right->id()],
        );

        return array_map(
            static fn (Order $order): Order => $order->copy(),
            array_slice($orders, 0, $limit),
        );
    }

    public function findByIdempotencyKey(string $customerId, string $key): ?Order
    {
        $indexKey = $this->indexKey($customerId, $key);
        $orderId = $this->idempotencyIndex[$indexKey] ?? null;

        return $orderId !== null && isset($this->orders[$orderId])
            ? $this->orders[$orderId]->copy()
            : null;
    }

    public function save(Order $order): void
    {
        $stored = $this->orders[$order->id()] ?? null;

        if ($stored === null) {
            if ($order->persistedVersion() !== 0) {
                throw new OptimisticLockException($order->id(), $order->persistedVersion());
            }
        } elseif ($order->persistedVersion() !== $stored->version()) {
            throw new OptimisticLockException(
                $order->id(),
                $order->persistedVersion(),
                $stored->version(),
            );
        }

        foreach ($order->pullStatusChanges() as $change) {
            $this->journal[] = $change;
        }

        $events = $order->pullEvents();

        $order->markPersisted();
        $this->orders[$order->id()] = $order->copy();
        $this->idempotencyIndex[$this->indexKey($order->customerId(), $order->idempotencyKey())] = $order->id();

        foreach ($events as $event) {
            $this->outbox->enqueue($event, $this->clock->now());
        }
    }

    /**
     * @return list<OrderStatusChanged>
     */
    public function journal(): array
    {
        return $this->journal;
    }

    public function resetJournal(): void
    {
        $this->journal = [];
    }

    private function indexKey(string $customerId, string $key): string
    {
        return $customerId . ':' . $key;
    }
}
