<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Services\ProductImageServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\DTO\ProductFilters;
use App\Exceptions\ProductNotFoundException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class ProductVisibilityTest extends PostgresTestCase
{
    private ProductServiceInterface $service;
    private ProductImageServiceInterface $images;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = app(ProductServiceInterface::class);
        $this->images = app(ProductImageServiceInterface::class);
    }

    public function test_inactive_product_is_not_visible_on_public_card(): void
    {
        $store = $this->createStore(active: true);
        $product = $this->createProduct($store, active: false);

        $this->expectException(ProductNotFoundException::class);

        $this->service->getPublic($product->id);
    }

    public function test_product_of_inactive_store_is_not_visible_on_public_card(): void
    {
        $store = $this->createStore(active: false);
        $product = $this->createProduct($store, active: true);

        $this->expectException(ProductNotFoundException::class);

        $this->service->getPublic($product->id);
    }

    public function test_active_product_of_active_store_is_visible_on_public_card(): void
    {
        $store = $this->createStore(active: true);
        $product = $this->createProduct($store, active: true);

        $result = $this->service->getPublic($product->id);

        self::assertSame($product->id, $result->id);
    }

    public function test_admin_card_still_shows_inactive_product(): void
    {
        $store = $this->createStore(active: true);
        $product = $this->createProduct($store, active: false);

        $result = $this->service->get($product->id, $store->id);

        self::assertSame($product->id, $result->id);
    }

    public function test_public_list_excludes_inactive_products_and_products_of_inactive_stores(): void
    {
        $activeStore = $this->createStore(active: true);
        $inactiveStore = $this->createStore(active: false);

        $visible = $this->createProduct($activeStore, active: true);
        $this->createProduct($activeStore, active: false);
        $this->createProduct($inactiveStore, active: true);

        $result = $this->service->list(new ProductFilters(publicOnly: true));

        self::assertSame(1, $result->total());
        self::assertSame(
            [$visible->id],
            array_map(fn (Product $product): string => $product->id, $result->items()),
        );
    }

    public function test_admin_list_shows_inactive_products(): void
    {
        $store = $this->createStore(active: true);
        $this->createProduct($store, active: true);
        $this->createProduct($store, active: false);

        $result = $this->service->list(new ProductFilters(
            storeId: $store->id,
            isActive: null,
            publicOnly: false,
        ));

        self::assertSame(2, $result->total());
    }

    public function test_images_of_inactive_product_are_not_public(): void
    {
        $store = $this->createStore(active: true);
        $product = $this->createProduct($store, active: false);
        $this->createImage($product);

        $this->expectException(ProductNotFoundException::class);

        $this->images->listPublic($product->id);
    }

    public function test_images_of_product_in_inactive_store_are_not_public(): void
    {
        $store = $this->createStore(active: false);
        $product = $this->createProduct($store, active: true);
        $this->createImage($product);

        $this->expectException(ProductNotFoundException::class);

        $this->images->getPublic($product->id, (string) Uuid::uuid7());
    }

    public function test_images_of_active_product_in_active_store_are_public(): void
    {
        $store = $this->createStore(active: true);
        $product = $this->createProduct($store, active: true);
        $image = $this->createImage($product);

        self::assertSame([$image->id], $this->images->listPublic($product->id)->modelKeys());
        self::assertSame($image->id, $this->images->getPublic($product->id, $image->id)->id);
    }

    private function createImage(Product $product): ProductImage
    {
        return ProductImage::create([
            'product_id' => $product->id,
            'object_key' => 'images/' . Uuid::uuid7() . '.jpg',
            'is_main' => true,
        ]);
    }

    private function createStore(bool $active): Store
    {
        return Store::create([
            'name' => 'Store ' . Uuid::uuid7(),
            'active' => $active,
        ]);
    }

    private function createProduct(Store $store, bool $active): Product
    {
        $category = Category::create([
            'store_id' => $store->id,
            'name' => 'Tools',
            'slug' => 'tools-' . Uuid::uuid7(),
        ]);

        return Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill-' . Uuid::uuid7(),
            'price_per_hour' => 500,
            'is_active' => $active,
        ]);
    }
}
