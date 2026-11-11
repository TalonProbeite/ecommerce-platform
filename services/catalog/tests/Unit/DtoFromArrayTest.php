<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\StoreFilters;
use App\DTO\UpdateCategoryData;
use App\DTO\UpdateProductData;
use Tests\TestCase;

class DtoFromArrayTest extends TestCase
{
    public function test_create_product_from_array_preserves_quantity_and_attributes(): void
    {
        $data = CreateProductData::fromArray([
            'name' => 'Drill',
            'slug' => 'drill',
            'category_id' => 'category-id',
            'price_per_hour' => 100,
            'quantity' => 7,
            'attributes' => [
                ['name' => 'Power', 'value' => '800 W'],
            ],
        ]);

        self::assertSame(7, $data->quantity);
        self::assertCount(1, $data->attributes);
        self::assertSame('Power', $data->attributes[0]->name);
        self::assertSame('800 W', $data->attributes[0]->value);
    }

    public function test_update_product_from_array_uses_key_presence_for_provided_flags(): void
    {
        $data = UpdateProductData::fromArray(['description' => null]);

        self::assertTrue($data->descriptionProvided);
        self::assertNull($data->description);
        self::assertFalse($data->nameProvided);
        self::assertNull($data->attributes);
    }

    public function test_update_category_from_array_detects_explicit_null_parent(): void
    {
        $data = UpdateCategoryData::fromArray(['parent_id' => null]);

        self::assertTrue($data->parentIdProvided);
        self::assertNull($data->parentId);
        self::assertFalse($data->nameProvided);
    }

    public function test_product_filters_from_array_applies_public_only_default(): void
    {
        $public = ProductFilters::fromArray([], true);
        $admin = ProductFilters::fromArray([]);

        self::assertTrue($public->publicOnly);
        self::assertNull($public->isActive);
        self::assertNull($public->isAvailable);
        self::assertFalse($admin->publicOnly);
        self::assertNull($admin->isActive);
        self::assertNull($admin->isAvailable);
    }

    public function test_store_filters_from_array_distinguishes_absent_and_null_active(): void
    {
        self::assertTrue(StoreFilters::fromArray([])->active);
        self::assertNull(StoreFilters::fromArray(['active' => null])->active);
        self::assertFalse(StoreFilters::fromArray(['active' => '0'])->active);
    }
}
