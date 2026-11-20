<?php

declare(strict_types=1);

namespace App\Application\Saga\Commands;

use Carbon\CarbonImmutable;

final readonly class StockReservedCommand
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $orderId,
        public string $storeId,
    ) {
    }
}
