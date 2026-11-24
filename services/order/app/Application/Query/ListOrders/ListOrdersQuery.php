<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrders;

use App\Domain\Order\OrderStatus;

final readonly class ListOrdersQuery
{
    public const int DEFAULT_LIMIT = 20;

    public function __construct(
        public ?string $customerId,
        public ?string $storeId,
        public ?OrderStatus $status,
        public ?string $cursor,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
