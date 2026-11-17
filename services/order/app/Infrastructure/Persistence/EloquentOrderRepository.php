<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\IdempotencyKeyAlreadyExists;
use App\Application\Exceptions\OptimisticLockException;
use App\Application\Ports\Clock;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\TransactionRunnerInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Outbox\OutboxWriterInterface;
use App\Infrastructure\Persistence\Models\OrderEventModel;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

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

    public function findByIdForUpdate(string $id): ?Order
    {
        $model = OrderModel::query()->lockForUpdate()->find($id);

        return $model !== null ? $this->mapper->toAggregate($model) : null;
    }

    /**
     * @return list<Order>
     */
    public function lockExpiredUnpaidForUpdate(CarbonImmutable $createdBefore, int $limit): array
    {
        $models = OrderModel::query()
            ->whereIn('status', [OrderStatus::STOCK_RESERVED->value, OrderStatus::PAYMENT_PENDING->value])
            ->where('created_at', '<', $createdBefore->format('Y-m-d H:i:s.uP'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->lock('FOR UPDATE SKIP LOCKED')
            ->get();

        $orders = [];

        foreach ($models as $model) {
            $orders[] = $this->mapper->toAggregate($model);
        }

        return $orders;
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

                try {
                    $model->save();
                } catch (QueryException $exception) {
                    if ($this->isIdempotencyKeyConflict($exception)) {
                        throw new IdempotencyKeyAlreadyExists(
                            $order->customerId(),
                            $order->idempotencyKey(),
                        );
                    }

                    throw $exception;
                }
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

    private function isIdempotencyKeyConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState !== '23505' && $sqlState !== '23000') {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'orders_customer_id_idempotency_key_unique')
            || (str_contains($message, 'customer_id') && str_contains($message, 'idempotency_key'));
    }
}
