<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\ProductSortField;
use App\Enums\SortDirection;

final readonly class ProductFilters
{
    /**
     * Create a new product filters instance.
     */
    public function __construct(
        public ?string $storeId = null,
        public ?string $categoryId = null,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public ?int $maxDeposit = null,
        public ?bool $isActive = true,
        public ?bool $isAvailable = true,
        public ProductSortField $sortBy = ProductSortField::CREATED_AT,
        public SortDirection $sortDirection = SortDirection::DESC,
        public int $limit = 20,
        public int $page = 1,
    ) {
    }
}
