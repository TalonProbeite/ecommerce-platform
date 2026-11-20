<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\ProductFilters;
use App\DTO\StoreFilters;
use App\Enums\ProductSortField;
use App\Enums\SortDirection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogFiltersTest extends TestCase
{
    #[Test]
    public function it_parses_product_sorting_and_falls_back_to_defaults(): void
    {
        $sorted = ProductFilters::fromArray([
            'sort' => 'price_per_hour',
            'sort_direction' => 'asc',
        ]);

        self::assertSame(ProductSortField::PRICE, $sorted->sortBy);
        self::assertSame(SortDirection::ASC, $sorted->sortDirection);

        $fallback = ProductFilters::fromArray([
            'sort' => 'unknown',
            'sort_direction' => 'sideways',
        ]);

        self::assertSame(ProductSortField::CREATED_AT, $fallback->sortBy);
        self::assertSame(SortDirection::DESC, $fallback->sortDirection);
    }

    #[Test]
    public function it_parses_boolean_product_filters(): void
    {
        $filters = ProductFilters::fromArray([
            'available_only' => '1',
            'is_active' => 'false',
        ]);

        self::assertTrue($filters->isAvailable);
        self::assertFalse($filters->isActive);

        $withoutAvailability = ProductFilters::fromArray(['available_only' => '0']);

        self::assertNull($withoutAvailability->isAvailable);
        self::assertNull($withoutAvailability->isActive);

        $withNullActive = ProductFilters::fromArray(['is_active' => null]);

        self::assertNull($withNullActive->isActive);
    }

    #[Test]
    public function it_casts_product_pagination_and_identifiers(): void
    {
        $filters = ProductFilters::fromArray([
            'store_id' => 123,
            'category_id' => 456,
            'min_price' => '10',
            'max_price' => '20',
            'max_deposit' => '30',
            'per_page' => '50',
            'page' => '3',
        ], true);

        self::assertSame('123', $filters->storeId);
        self::assertSame('456', $filters->categoryId);
        self::assertSame(10, $filters->minPrice);
        self::assertSame(20, $filters->maxPrice);
        self::assertSame(30, $filters->maxDeposit);
        self::assertSame(50, $filters->limit);
        self::assertSame(3, $filters->page);
        self::assertTrue($filters->publicOnly);
    }

    #[Test]
    public function it_uses_product_pagination_defaults(): void
    {
        $filters = ProductFilters::fromArray([]);

        self::assertSame(20, $filters->limit);
        self::assertSame(1, $filters->page);
        self::assertNull($filters->storeId);
        self::assertNull($filters->categoryId);
        self::assertNull($filters->minPrice);
        self::assertNull($filters->maxPrice);
        self::assertNull($filters->maxDeposit);
        self::assertFalse($filters->publicOnly);
    }

    #[Test]
    public function it_distinguishes_absent_and_null_store_activity(): void
    {
        self::assertTrue(StoreFilters::fromArray([])->active);
        self::assertNull(StoreFilters::fromArray(['active' => null])->active);
        self::assertFalse(StoreFilters::fromArray(['active' => 'false'])->active);
        self::assertTrue(StoreFilters::fromArray(['active' => 'true'])->active);
    }

    #[Test]
    public function it_casts_store_pagination(): void
    {
        $filters = StoreFilters::fromArray(['per_page' => '15', 'page' => '2']);

        self::assertSame(15, $filters->limit);
        self::assertSame(2, $filters->page);
    }
}
