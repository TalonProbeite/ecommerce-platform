<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\OptimisticLockException;
use App\Application\Ports\Clock;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Order\Order;
use App\Infrastructure\Messaging\Outbox\OutboxWriterInterface;
use App\Infrastructure\Persistence\Models\OrderEventModel;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\OrderModel;

final readonly class EloquentOrderRepository implements OrderRepository
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private OutboxWriterInterface $outbox,
        private OrderMapper $mapper,
        private Clock $clock,
    ) {
    }

    public function findById(string $id): ?Order
    {
        $model = OrderModel::query()->find($id);

        return $model !== null ? $this->mapper->toAggregate($model) : null;
    }

    public function findByIdempotencyKey(string $customerId, string $key): ?Order
    {
        $model = OrderModel::query()
            ->where('customer_id', $customerId)
            ->where('idempotency_key', $key)
            ->first();

        return $model !== null ? $this->mapper->toAggregate($model) : null;
    }

    public function save(Order $order): void
    {
        $this->transactions->run(function () use ($order): void {
            $now = $this->clock->now();
            $existing = OrderModel::query()->find($order->id());

            if ($existing === null) {
                $model = new OrderModel($this->mapper->orderAttributes($order, $now));
                $model->save();
            } else {
                $expectedVersion = $order->persistedVersion();
                $attributes = $this->mapper->orderAttributes($order, $now);
                unset($attributes['id'], $attributes['created_at']);

                $affected = OrderModel::query()
                    ->whereKey($order->id())
                    ->where('version', $expectedVersion)
                    ->update($attributes);

                if ($affected === 0) {
                    $actualVersion = OrderModel::query()->whereKey($order->id())->value('version');

                    throw new OptimisticLockException(
                        $order->id(),
                        $expectedVersion,
                        $actualVersion !== null ? (int) $actualVersion : null,
                    );
                }
            }

            foreach ($order->items() as $item) {
                OrderItemModel::query()->updateOrCreate(
                    ['id' => $item->lineId()],
                    $this->mapper->itemAttributes($item, $order->id(), $order->createdAt(), $now),
                );
            }

            foreach ($order->pullStatusChanges() as $change) {
                OrderEventModel::query()->create($this->mapper->statusChangeAttributes($change));
            }

            foreach ($order->pullEvents() as $event) {
                $this->outbox->enqueue($event, $now);
            }

            $order->markPersisted();
        });
    }
}
