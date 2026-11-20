<?php

declare(strict_types=1);

namespace App\Application\Catalog\SyncProductSnapshot\Commands;

use Carbon\CarbonImmutable;

final readonly class ProductUpsertCommand
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $productId,
        public string $storeId,
        public string $categoryId,
        public string $name,
        public int $pricePerHour,
        public int $depositAmount,
        public bool $isActive,
    ) {
    }
}
