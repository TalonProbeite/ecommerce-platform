<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\OrderEventReadRepository;
use App\Application\Query\ListOrderEvents\ListOrderEventsCriteria;
use App\Application\Query\ListOrderEvents\OrderEventCursor;
use App\Application\Query\ListOrderEvents\OrderEventItem;
use App\Application\Query\ListOrderEvents\OrderEventPage;
use App\Infrastructure\Persistence\Models\OrderEventModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class EloquentOrderEventReadRepository implements OrderEventReadRepository
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function storeIdOf(string $orderId): ?string
    {
        $storeId = OrderModel::query()->whereKey($orderId)->value('store_id');

        return is_string($storeId) && $storeId !== '' ? $storeId : null;
    }

    public function list(ListOrderEventsCriteria $criteria): OrderEventPage
    {
        $query = OrderEventModel::query()->where('order_id', $criteria->orderId);

        if ($criteria->cursor !== null) {
            $cursorAt = $criteria->cursor->occurredAt->format(self::TIMESTAMP_FORMAT);
            $cursorId = (int) $criteria->cursor->id;

            $query->where(function (Builder $nested) use ($cursorAt, $cursorId): void {
                $nested->where('occurred_at', '>', $cursorAt)
                    ->orWhere(function (Builder $sameTimestamp) use ($cursorAt, $cursorId): void {
                        $sameTimestamp->where('occurred_at', $cursorAt)
                            ->where('id', '>', $cursorId);
                    });
            });
        }

        $models = $query
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($criteria->limit + 1)
            ->get();

        $hasMore = $models->count() > $criteria->limit;

        if ($hasMore) {
            $models = $models->slice(0, $criteria->limit)->values();
        }

        $events = [];

        foreach ($models as $model) {
            $events[] = new OrderEventItem(
                id: (string) $model->getAttribute('id'),
                fromStatus: $this->nullableString($model->getAttribute('from_status')),
                toStatus: (string) $model->getAttribute('to_status'),
                orderItemId: $this->nullableString($model->getAttribute('order_item_id')),
                actorRole: (string) $model->getAttribute('actor_role'),
                occurredAt: $this->immutableDate($model->getAttribute('occurred_at')),
            );
        }

        $nextCursor = null;

        if ($hasMore && $events !== []) {
            $last = $events[array_key_last($events)];
            $nextCursor = (new OrderEventCursor($last->occurredAt, $last->id))->encode();
        }

        return new OrderEventPage($events, $nextCursor);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function immutableDate(mixed $value): CarbonImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            throw new LogicException('The order event row has no occurred_at value.');
        }

        return CarbonImmutable::instance($value);
    }
}
