<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductAttributeResource;
use App\Http\Resources\ProductImageResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockResource;
use App\Http\Resources\StoreResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ResourcesDoNotQueryTest extends TestCase
{
    public function test_store_resource_does_not_execute_queries(): void
    {
        $store = new Store([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Hardware Store',
            'description' => 'A local store',
            'location' => 'Main St 12',
            'active' => true,
        ]);
        $store->created_at = now();
        $store->updated_at = now();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new StoreResource($store);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }

    public function test_category_resource_does_not_execute_queries(): void
    {
        $category = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'name' => 'Power Tools',
            'slug' => 'power-tools',
        ]);
        $category->created_at = now();
        $category->updated_at = now();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new CategoryResource($category);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }

    public function test_product_attribute_resource_does_not_execute_queries(): void
    {
        $attribute = new ProductAttribute([
            'name' => 'Color',
            'value' => 'Red',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new ProductAttributeResource($attribute);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }

    public function test_product_image_resource_does_not_execute_queries(): void
    {
        $image = new ProductImage([
            'id' => (string) Uuid::uuid7(),
            'product_id' => (string) Uuid::uuid7(),
            'object_key' => 'images/sample.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12345,
            'is_main' => true,
        ]);
        $image->created_at = now();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new ProductImageResource($image);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }

    public function test_stock_resource_does_not_execute_queries(): void
    {
        $stock = new Stock([
            'product_id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'quantity' => 15,
            'reserved' => 3,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new StockResource($stock);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }

    public function test_product_resource_does_not_execute_queries(): void
    {
        $product = new Product([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'category_id' => (string) Uuid::uuid7(),
            'name' => 'Rotary Hammer',
            'slug' => 'rotary-hammer',
            'description' => 'Heavy duty hammer',
            'price_per_hour' => 1200,
            'deposit_amount' => 5000,
            'is_active' => true,
        ]);
        $product->created_at = now();
        $product->updated_at = now();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resource = new ProductResource($product);
        $array = $resource->toArray(request());

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertIsArray($array);
        self::assertSame(0, count($queries));
    }
}
