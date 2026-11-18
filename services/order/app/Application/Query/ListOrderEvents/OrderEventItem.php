<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrderEvents;

use Carbon\CarbonImmutable;

final readonly class OrderEventItem
{
    public function __construct(
        public string $id,
        public ?string $fromStatus,
        public string $toStatus,
        public ?string $orderItemId,
        public string $actorRole,
        public CarbonImmutable $occurredAt,
    ) {
    }
}
