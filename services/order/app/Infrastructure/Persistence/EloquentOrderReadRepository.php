<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\OrderReadRepository;
use App\Application\Query\ListOrders\OrderListCriteria;
use App\Application\Query\ListOrders\OrderListResult;
use App\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentOrderReadRepository implements OrderReadRepository
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function __construct(private OrderMapper $mapper)
    {
    }

    public function list(OrderListCriteria $criteria): OrderListResult
    {
        $query = OrderModel::query();

        if ($criteria->customerId !== null) {
            $query->where('customer_id', $criteria->customerId);
        }

        if ($criteria->storeId !== null) {
            $query->where('store_id', $criteria->storeId);
        }

        if ($criteria->status !== null) {
            $query->where('status', $criteria->status->value);
        }

        if ($criteria->cursor !== null) {
            $cursorAt = $criteria->cursor->createdAt->format(self::TIMESTAMP_FORMAT);
            $cursorId = $criteria->cursor->id;

            $query->where(function (Builder $nested) use ($cursorAt, $cursorId): void {
                $nested->where('created_at', '<', $cursorAt)
                    ->orWhere(function (Builder $sameTimestamp) use ($cursorAt, $cursorId): void {
                        $sameTimestamp->where('created_at', $cursorAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        $models = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($criteria->limit)
            ->get();

        $orders = [];

        foreach ($models as $model) {
            $orders[] = $this->mapper->toAggregate($model);
        }

        return new OrderListResult($orders);
    }
}
