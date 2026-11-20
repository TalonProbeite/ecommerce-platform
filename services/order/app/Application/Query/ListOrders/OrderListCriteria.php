<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrders;

use App\Domain\Order\OrderStatus;

final readonly class OrderListCriteria
{
    public function __construct(
        public ?string $customerId,
        public ?string $storeId,
        public ?OrderStatus $status,
        public ?OrderCursor $cursor,
        public int $limit,
    ) {
    }
}
