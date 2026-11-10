<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class UpdateStockData
{
    public function __construct(
        public ?int $quantityDelta = null,
        public ?int $reservedDelta = null,
    ) {
    }
}
