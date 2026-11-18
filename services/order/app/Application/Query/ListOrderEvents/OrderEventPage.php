<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrderEvents;

final readonly class OrderEventPage
{
    /**
     * @param list<OrderEventItem> $events
     */
    public function __construct(
        public array $events,
        public ?string $nextCursor,
    ) {
    }
}
