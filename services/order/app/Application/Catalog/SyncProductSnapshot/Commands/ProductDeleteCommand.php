<?php

declare(strict_types=1);

namespace App\Application\Catalog\SyncProductSnapshot\Commands;

use Carbon\CarbonImmutable;

final readonly class ProductDeleteCommand
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $productId,
        public string $storeId,
    ) {
    }
}
