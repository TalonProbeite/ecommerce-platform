<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrders;

use App\Application\Exceptions\InvalidCursor;
use App\Application\Ports\OrderReadRepository;

final readonly class ListOrdersUseCase
{
    public function __construct(private OrderReadRepository $orders)
    {
    }

    public function execute(ListOrdersQuery $query): OrderPage
    {
        $cursor = null;

        if ($query->cursor !== null && $query->cursor !== '') {
            $cursor = OrderCursor::decode($query->cursor);

            if ($cursor === null) {
                throw new InvalidCursor();
            }
        }

        $result = $this->orders->list(new OrderListCriteria(
            customerId: $query->customerId,
            storeId: $query->storeId,
            status: $query->status,
            cursor: $cursor,
            limit: $query->limit + 1,
        ));

        $orders = $result->orders;
        $hasMore = count($orders) > $query->limit;

        if ($hasMore) {
            $orders = array_slice($orders, 0, $query->limit);
        }

        $nextCursor = null;

        if ($hasMore && $orders !== []) {
            $last = $orders[array_key_last($orders)];
            $nextCursor = OrderCursor::forOrder($last)->encode();
        }

        return new OrderPage($orders, $nextCursor);
    }
}
