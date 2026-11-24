<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Exceptions\InvalidCursor;
use App\Application\Ports\OverdueRentalReadRepository;
use App\Application\Query\OverdueRentals\OverdueRentalCriteria;
use App\Application\Query\OverdueRentals\OverdueRentalCursor;
use App\Application\Query\OverdueRentals\OverdueRentalItem;
use App\Application\Query\OverdueRentals\OverdueRentalPage;
use App\Domain\Order\ItemStatus;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class EloquentOverdueRentalReadRepository implements OverdueRentalReadRepository
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function find(OverdueRentalCriteria $criteria, ?string $cursor, int $limit): OverdueRentalPage
    {
        $query = OrderItemModel::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select([
                'order_items.id',
                'order_items.order_id',
                'order_items.product_id',
                'order_items.product_name',
                'order_items.quantity',
                'order_items.rental_end_at',
                'orders.customer_id',
                'orders.store_id',
            ]);

        $query->where('order_items.item_status', ItemStatus::ISSUED->value)
            ->whereNull('order_items.returned_at')
            ->where('order_items.rental_end_at', '<', $criteria->now->utc()->format(self::TIMESTAMP_FORMAT));

        if ($criteria->storeId !== null) {
            $query->where('orders.store_id', $criteria->storeId);
        }

        if ($cursor !== null) {
            $decoded = OverdueRentalCursor::decode($cursor);

            if ($decoded === null) {
                throw new InvalidCursor();
            }

            $cursorAt = $decoded->rentalEndAt->format(self::TIMESTAMP_FORMAT);
            $cursorId = $decoded->id;

            $query->where(function (Builder $nested) use ($cursorAt, $cursorId): void {
                $nested->where('order_items.rental_end_at', '>', $cursorAt)
                    ->orWhere(function (Builder $sameTimestamp) use ($cursorAt, $cursorId): void {
                        $sameTimestamp->where('order_items.rental_end_at', $cursorAt)
                            ->where('order_items.id', '>', $cursorId);
                    });
            });
        }

        $models = $query
            ->orderBy('order_items.rental_end_at')
            ->orderBy('order_items.id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $models->count() > $limit;

        if ($hasMore) {
            $models = $models->slice(0, $limit)->values();
        }

        $items = [];

        foreach ($models as $model) {
            $items[] = new OverdueRentalItem(
                orderId: (string) $model->getAttribute('order_id'),
                itemId: (string) $model->getAttribute('id'),
                productId: (string) $model->getAttribute('product_id'),
                productName: (string) $model->getAttribute('product_name'),
                storeId: (string) $model->getAttribute('store_id'),
                customerId: (string) $model->getAttribute('customer_id'),
                quantity: (int) $model->getAttribute('quantity'),
                rentalEndAt: $this->immutableDate($model->getAttribute('rental_end_at')),
                overdueHours: 0,
            );
        }

        $nextCursor = null;

        if ($hasMore && $items !== []) {
            $last = $items[array_key_last($items)];
            $nextCursor = (new OverdueRentalCursor($last->rentalEndAt, $last->itemId))->encode();
        }

        return new OverdueRentalPage($items, $nextCursor);
    }

    private function immutableDate(mixed $value): CarbonImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            throw new LogicException('The overdue rental row has no rental_end_at value.');
        }

        return CarbonImmutable::instance($value);
    }
}
