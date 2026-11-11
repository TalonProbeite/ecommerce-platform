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
        public ?bool $isActive = null,
        public ?bool $isAvailable = null,
        public ProductSortField $sortBy = ProductSortField::CREATED_AT,
        public SortDirection $sortDirection = SortDirection::DESC,
        public int $limit = 20,
        public int $page = 1,
        public bool $publicOnly = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, bool $publicOnly = false): self
    {
        $availableOnly = array_key_exists('available_only', $data) && $data['available_only'] !== null
            ? (bool) filter_var($data['available_only'], FILTER_VALIDATE_BOOLEAN)
            : false;

        $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null
            ? (bool) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $sortBy = ProductSortField::CREATED_AT;
        if (isset($data['sort']) && is_string($data['sort'])) {
            $sortBy = ProductSortField::tryFrom($data['sort']) ?? ProductSortField::CREATED_AT;
        }

        $sortDirection = SortDirection::DESC;
        if (isset($data['sort_direction']) && is_string($data['sort_direction'])) {
            $sortDirection = SortDirection::tryFrom($data['sort_direction']) ?? SortDirection::DESC;
        }

        return new self(
            storeId: isset($data['store_id']) ? (string) $data['store_id'] : null,
            categoryId: isset($data['category_id']) ? (string) $data['category_id'] : null,
            minPrice: isset($data['min_price']) ? (int) $data['min_price'] : null,
            maxPrice: isset($data['max_price']) ? (int) $data['max_price'] : null,
            maxDeposit: isset($data['max_deposit']) ? (int) $data['max_deposit'] : null,
            isActive: $isActive,
            isAvailable: $availableOnly ? true : null,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            limit: isset($data['per_page']) ? (int) $data['per_page'] : 20,
            page: isset($data['page']) ? (int) $data['page'] : 1,
            publicOnly: $publicOnly,
        );
    }
}
