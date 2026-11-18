<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrderEvents;

final readonly class ListOrderEventsQuery
{
    public const int DEFAULT_LIMIT = 50;

    public function __construct(
        public string $orderId,
        public ?string $cursor,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
