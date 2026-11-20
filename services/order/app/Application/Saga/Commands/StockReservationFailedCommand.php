<?php

declare(strict_types=1);

namespace App\Application\Saga\Commands;

use App\Application\Saga\StockRejectionReason;
use Carbon\CarbonImmutable;

final readonly class StockReservationFailedCommand
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $orderId,
        public string $storeId,
        public StockRejectionReason $reason,
    ) {
    }
}
