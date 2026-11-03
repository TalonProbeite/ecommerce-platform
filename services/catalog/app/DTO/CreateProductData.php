<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Catalog input only; monetary amounts are integer kopecks, with rent priced per hour.
 */
final readonly class CreateProductData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $categoryId,
        public int $pricePerHour,
        public ?string $description = null,
        public int $depositAmount = 0,
        public bool $isActive = true,
    ) {
    }
}
