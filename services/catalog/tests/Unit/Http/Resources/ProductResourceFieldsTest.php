<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ProductResourceFieldsTest extends TestCase
{
    public function test_list_product_without_relations(): void
    {
        $product = new Product([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'category_id' => (string) Uuid::uuid7(),
            'name' => 'Cordless Drill',
            'slug' => 'cordless-drill',
            'description' => 'Powerful 18V drill',
            'price_per_hour' => 450,
            'deposit_amount' => 2000,
            'is_active' => true,
        ]);
        $product->created_at = now();
        $product->updated_at = now();

        $dataWithoutMainImage = (new ProductResource($product))->response()->getData(true)['data'];

        self::assertArrayHasKey('id', $dataWithoutMainImage);
        self::assertArrayHasKey('store_id', $dataWithoutMainImage);
        self::assertArrayHasKey('category_id', $dataWithoutMainImage);
        self::assertArrayHasKey('name', $dataWithoutMainImage);
        self::assertArrayHasKey('slug', $dataWithoutMainImage);
        self::assertArrayHasKey('description', $dataWithoutMainImage);
        self::assertArrayHasKey('price_per_hour', $dataWithoutMainImage);
        self::assertArrayHasKey('deposit_amount', $dataWithoutMainImage);
        self::assertArrayHasKey('is_active', $dataWithoutMainImage);
        self::assertArrayHasKey('created_at', $dataWithoutMainImage);
        self::assertArrayHasKey('updated_at', $dataWithoutMainImage);
        self::assertArrayHasKey('main_image_url', $dataWithoutMainImage);
        self::assertNull($dataWithoutMainImage['main_image_url']);
        self::assertArrayNotHasKey('attributes', $dataWithoutMainImage);
        self::assertArrayNotHasKey('images', $dataWithoutMainImage);
        self::assertArrayNotHasKey('stock', $dataWithoutMainImage);
        self::assertArrayNotHasKey('category', $dataWithoutMainImage);

        // With mainImage loaded and category_name present
        $mainImage = new ProductImage([
            'id' => (string) Uuid::uuid7(),
            'product_id' => $product->id,
            'object_key' => 'catalog/products/main.png',
            'mime_type' => 'image/png',
            'size' => 4096,
            'is_main' => true,
        ]);
        $product->setRelation('mainImage', $mainImage);
        $product->category_name = 'Drills';

        $dataWithMainImage = (new ProductResource($product))->response()->getData(true)['data'];

        self::assertNotNull($dataWithMainImage['main_image_url']);
        self::assertSame($mainImage->url(), $dataWithMainImage['main_image_url']);
        self::assertSame('Drills', $dataWithMainImage['category_name']);
        self::assertArrayNotHasKey('attributes', $dataWithMainImage);
        self::assertArrayNotHasKey('images', $dataWithMainImage);
        self::assertArrayNotHasKey('stock', $dataWithMainImage);
        self::assertArrayNotHasKey('category', $dataWithMainImage);
    }

    public function test_card_product_with_relations(): void
    {
        $product = new Product([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'category_id' => (string) Uuid::uuid7(),
            'name' => 'Cordless Drill',
            'slug' => 'cordless-drill',
            'description' => 'Powerful 18V drill',
            'price_per_hour' => 450,
            'deposit_amount' => 2000,
            'is_active' => true,
        ]);
        $product->created_at = now();
        $product->updated_at = now();

        $category = new Category([
            'id' => $product->category_id,
            'name' => 'Power Drills',
        ]);
        $attribute = new ProductAttribute([
            'product_id' => $product->id,
            'name' => 'Voltage',
            'value' => '18V',
        ]);
        $image = new ProductImage([
            'id' => (string) Uuid::uuid7(),
            'product_id' => $product->id,
            'object_key' => 'catalog/products/card-image.png',
            'mime_type' => 'image/png',
            'size' => 8192,
            'is_main' => true,
        ]);
        $stock = new Stock([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'quantity' => 10,
            'reserved' => 2,
        ]);

        $product->setRelation('category', $category);
        $product->setRelation('attributes', new Collection([$attribute]));
        $product->setRelation('images', new Collection([$image]));
        $product->setRelation('stock', $stock);

        $data = (new ProductResource($product))->response()->getData(true)['data'];

        self::assertArrayHasKey('category', $data);
        self::assertSame(['id' => $category->id, 'name' => 'Power Drills'], $data['category']);

        self::assertArrayHasKey('attributes', $data);
        self::assertCount(1, $data['attributes']);
        self::assertSame([['name' => 'Voltage', 'value' => '18V']], $data['attributes']);

        self::assertArrayHasKey('images', $data);
        self::assertCount(1, $data['images']);
        self::assertSame($image->id, $data['images'][0]['id']);
        self::assertSame($image->url(), $data['images'][0]['url']);

        self::assertArrayHasKey('stock', $data);
        self::assertSame(10, $data['stock']['quantity']);
        self::assertSame(2, $data['stock']['reserved']);
        self::assertSame(8, $data['stock']['available']);

        $json = (string) json_encode($data);
        self::assertStringNotContainsString('object_key', $json);
        self::assertStringNotContainsString('path', $json);
    }
}
