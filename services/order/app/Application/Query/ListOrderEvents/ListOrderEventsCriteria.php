<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrderEvents;

final readonly class ListOrderEventsCriteria
{
    public function __construct(
        public string $orderId,
        public ?OrderEventCursor $cursor,
        public int $limit,
    ) {
    }
}
