<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\CreateStoreData;
use App\DTO\ProductFilters;
use App\DTO\StoreFilters;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Enums\ProductSortField;
use App\Enums\SortDirection;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductAttribute;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Repositories\StoreRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class CatalogRepositoriesTest extends PostgresTestCase
{
    private Store $store;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::create(['name' => 'Tools']);
        $this->category = Category::create(['store_id' => $this->store->id, 'name' => 'Tools', 'slug' => 'tools']);
        $this->product = $this->product('drill');
    }

    private function product(string $slug, array $values = []): Product
    {
        return Product::create(array_replace([
            'store_id' => $this->store->id, 'category_id' => $this->category->id,
            'name' => $slug, 'slug' => $slug, 'price_per_hour' => 100,
        ], $values));
    }

    public function test_store_save_find_filter_paginate_and_soft_delete(): void
    {
        $repository = new StoreRepository();
        $store = $repository->createStore(new CreateStoreData(name: 'Other', active: false));
        self::assertInstanceOf(Store::class, $repository->findByID($store->id));
        $repository->updateStore($store->id, new UpdateStoreData(name: 'Updated'));
        self::assertSame('Updated', $repository->findByID($store->id)->name);
        $active = $repository->findAll(new StoreFilters());
        self::assertInstanceOf(LengthAwarePaginator::class, $active);
        self::assertSame([$this->store->id], $active->getCollection()->modelKeys());
        self::assertSame([$store->id], $repository->findAll(new StoreFilters(active: false))->getCollection()->modelKeys());
        $page = $repository->findAll(new StoreFilters(active: null, limit: 1, page: 2));
        self::assertSame(2, $page->total());
        self::assertSame(2, $page->currentPage());
        self::assertCount(1, $page->items());
        self::assertTrue($this->store->products->contains($this->product));
        self::assertTrue($this->store->categories->contains($this->category));
        $repository->deleteStore($store->id);
        self::assertNull($repository->findByID($store->id));
        self::assertTrue(Store::withTrashed()->findOrFail($store->id)->trashed());
        self::assertNull($repository->findByID((string) Uuid::uuid7()));
    }

    public function test_store_delete_is_rejected_while_active_reservations_exist(): void
    {
        $repository = new StoreRepository();

        StockReservation::create([
            'order_id' => (string) Uuid::uuid7(),
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        try {
            $repository->deleteStore($this->store->id);
            self::fail('Deleting a store with active reservations must be rejected.');
        } catch (StoreHasActiveRentalsException $exception) {
            self::assertSame($this->store->id, $exception->storeID);
        }

        self::assertNotNull($repository->findByID($this->store->id));
    }

    public function test_categories_scope_tree_save_update_and_soft_delete(): void
    {
        $repository = new CategoryRepository();
        $child = new Category(['store_id' => $this->store->id, 'parent_id' => $this->category->id, 'name' => 'Child', 'slug' => 'child']);
        self::assertSame($child, $repository->save($child));
        $other = Store::create(['name' => 'Other']);
        Category::create(['store_id' => $other->id, 'name' => 'Other', 'slug' => 'tools']);
        self::assertInstanceOf(Collection::class, $repository->findByStore($this->store->id));
        self::assertEqualsCanonicalizing([$this->category->id, $child->id], $repository->findByStore($this->store->id)->modelKeys());
        self::assertSame([$this->category->id], $repository->findRoots($this->store->id)->modelKeys());
        self::assertSame([$child->id], $repository->findChildren($this->category->id, $this->store->id)->modelKeys());
        self::assertTrue($repository->findChildren($this->category->id, $other->id)->isEmpty());
        self::assertTrue($child->parent->is($this->category));
        $child->name = 'Updated';
        $repository->save($child);
        self::assertSame('Updated', $repository->findByID($child->id)->name);
        $repository->delete($child);
        self::assertNull($repository->findByID($child->id));
        self::assertTrue(Category::withTrashed()->findOrFail($child->id)->trashed());
        self::assertTrue($repository->findChildren($this->category->id, $this->store->id)->isEmpty());
    }

    public function test_products_save_tenant_lookup_slug_and_soft_delete(): void
    {
        $repository = new ProductRepository();
        $product = new Product(['store_id' => $this->store->id, 'category_id' => $this->category->id, 'name' => 'Saw', 'slug' => 'saw', 'price_per_hour' => 300]);
        self::assertSame($product, $repository->save($product));
        self::assertTrue($repository->findById($product->id)->is($product));
        self::assertTrue($repository->findById($product->id, $this->store->id)->is($product));
        $otherStore = (string) Uuid::uuid7();
        self::assertNull($repository->findById($product->id, $otherStore));
        self::assertNull($repository->findDetailedById($product->id, $otherStore));
        self::assertTrue($repository->existsBySlug('saw', $this->store->id));
        self::assertFalse($repository->existsBySlug('saw', $otherStore));
        self::assertFalse($repository->existsBySlug('saw', $this->store->id, $product->id));
        $product->price_per_hour = 450;
        $repository->save($product);
        self::assertSame(450, $repository->findById($product->id)->price_per_hour);
        $repository->delete($product);
        self::assertNull($repository->findById($product->id));
        self::assertNull($repository->findDetailedById($product->id));
        self::assertTrue(Product::withTrashed()->findOrFail($product->id)->trashed());
        self::assertTrue($repository->existsBySlug('saw', $this->store->id));
    }

    public function test_product_filters_sorting_pagination_and_main_images(): void
    {
        $repository = new ProductRepository();
        $low = $this->product('low', ['price_per_hour' => 200, 'deposit_amount' => 500, 'rating' => '4.50']);
        $high = $this->product('high', ['price_per_hour' => 300, 'deposit_amount' => 500, 'rating' => '4.75']);
        $this->product('inactive', ['is_active' => false, 'price_per_hour' => 200, 'rating' => '4.50']);
        $this->product('deposit', ['price_per_hour' => 200, 'deposit_amount' => 501, 'rating' => '4.50']);
        $this->product('rating', ['price_per_hour' => 200, 'rating' => '3.99']);
        $this->product('expensive', ['price_per_hour' => 301, 'rating' => '4.50']);
        $otherCategory = Category::create(['store_id' => $this->store->id, 'name' => 'Other', 'slug' => 'other']);
        $this->product('other-category', ['category_id' => $otherCategory->id, 'price_per_hour' => 200, 'rating' => '4.50']);
        $otherStore = Store::create(['name' => 'Other']);
        $category = Category::create(['store_id' => $otherStore->id, 'name' => 'Tools', 'slug' => 'tools']);
        $this->product('other-store', ['store_id' => $otherStore->id, 'category_id' => $category->id, 'price_per_hour' => 200, 'rating' => '4.50']);
        ProductImage::create(['product_id' => $high->id, 'path' => 'main.jpg', 'is_main' => true]);
        ProductImage::create(['product_id' => $high->id, 'path' => 'side.jpg']);
        $filters = new ProductFilters(storeId: $this->store->id, categoryId: $this->category->id, minPrice: 200, maxPrice: 300, maxDeposit: 500, minRating: 4.0, sortBy: ProductSortField::PRICE, sortDirection: SortDirection::DESC, limit: 1);
        $page = $repository->findByFilters($filters);
        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertSame(2, $page->total());
        self::assertSame([$high->id], $page->getCollection()->modelKeys());
        self::assertSame(['main.jpg'], $page->items()[0]->images->pluck('path')->all());
        $second = $repository->findByFilters(new ProductFilters(...array_replace(get_object_vars($filters), ['page' => 2])));
        self::assertSame([$low->id], $second->getCollection()->modelKeys());
        self::assertSame(2, $second->currentPage());
        self::assertSame(1, $repository->findByFilters(new ProductFilters(isActive: false))->total());
        self::assertSame(9, $repository->findByFilters(new ProductFilters(isActive: null))->total());
    }

    public function test_product_store_filter_without_category_and_stable_sort_pagination(): void
    {
        $repository = new ProductRepository();
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create(['store_id' => $otherStore->id, 'name' => 'Tools', 'slug' => 'tools']);
        $foreign = $this->product('foreign', ['store_id' => $otherStore->id, 'category_id' => $otherCategory->id]);
        $samePrice = $this->product('same-price');
        $expected = [$this->product->id, $samePrice->id];
        sort($expected);
        foreach ([SortDirection::ASC, SortDirection::DESC] as $direction) {
            $page = $repository->findByFilters(new ProductFilters(storeId: $this->store->id, sortBy: ProductSortField::PRICE, sortDirection: $direction, limit: 1));
            self::assertSame(2, $page->total());
            self::assertSame([$expected[0]], $page->getCollection()->modelKeys());
            $second = $repository->findByFilters(new ProductFilters(storeId: $this->store->id, sortBy: ProductSortField::PRICE, sortDirection: $direction, limit: 1, page: 2));
            self::assertSame([$expected[1]], $second->getCollection()->modelKeys());
        }
        self::assertSame([$foreign->id], $repository->findByFilters(new ProductFilters(storeId: $otherStore->id))->getCollection()->modelKeys());
        $repository->delete($samePrice);
        self::assertSame(1, $repository->findByFilters(new ProductFilters(storeId: $this->store->id))->total());
        self::assertTrue($repository->findByFilters(new ProductFilters(storeId: (string) Uuid::uuid7()))->isEmpty());
    }

    public function test_detailed_product_eager_loads_exact_related_data(): void
    {
        ProductAttribute::create(['product_id' => $this->product->id, 'name' => 'Power', 'value' => '800 W']);
        ProductImage::create(['product_id' => $this->product->id, 'path' => 'second.jpg', 'sort_order' => 2]);
        ProductImage::create(['product_id' => $this->product->id, 'path' => 'first.jpg', 'sort_order' => 1]);
        Stock::create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 10, 'reserved' => 3]);
        $product = (new ProductRepository())->findDetailedById($this->product->id, $this->store->id);
        foreach (['category', 'attributes', 'images', 'stock'] as $relation) {
            self::assertTrue($product->relationLoaded($relation));
        }
        self::assertTrue($product->category->is($this->category));
        self::assertSame(['800 W'], $product->attributes->pluck('value')->all());
        self::assertSame(['first.jpg', 'second.jpg'], $product->images->pluck('path')->all());
        self::assertSame(7, $product->stock->available);
        self::assertSame(100, $product->price_per_hour);
        self::assertSame('0.00', $product->rating);
        self::assertTrue($product->is_active);
    }

    public function test_images_save_sort_scope_update_and_hard_delete(): void
    {
        $repository = new ProductImageRepository();
        $other = $this->product('other');
        $image = new ProductImage(['product_id' => $this->product->id, 'path' => 'back.jpg', 'sort_order' => 5]);
        self::assertSame($image, $repository->save($image));
        $first = ProductImage::create(['product_id' => $this->product->id, 'path' => 'front.jpg', 'sort_order' => 1, 'is_main' => true]);
        self::assertSame([$first->id, $image->id], $repository->findByProduct($this->product->id)->modelKeys());
        self::assertNull($repository->findById($image->id, $other->id));
        self::assertTrue($repository->findByProduct($other->id)->isEmpty());
        self::assertTrue($image->product->is($this->product));
        $image->path = 'updated.jpg';
        $repository->save($image);
        self::assertSame('updated.jpg', $repository->findById($image->id, $this->product->id)->path);
        self::assertSame(5, $image->fresh()->sort_order);
        self::assertFalse($image->fresh()->is_main);
        $repository->delete($image);
        self::assertNull($repository->findById($image->id, $this->product->id));
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_stock_save_store_isolation_update_boundaries_and_hard_delete(): void
    {
        $repository = new StockRepository();
        $stock = new Stock(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 10, 'reserved' => 3]);
        self::assertSame($stock, $repository->save($stock));
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create(['store_id' => $otherStore->id, 'name' => 'Tools', 'slug' => 'tools']);
        $otherProduct = $this->product('foreign-stock', ['store_id' => $otherStore->id, 'category_id' => $otherCategory->id]);
        $repository->save(new Stock(['store_id' => $otherStore->id, 'product_id' => $otherProduct->id, 'quantity' => 5]));
        self::assertInstanceOf(Stock::class, $repository->findByProductAndStore($this->product->id, $this->store->id));
        self::assertNull($repository->findByProductAndStore($this->product->id, $otherStore->id));
        self::assertSame([$stock->id], $repository->findByStore($this->store->id)->modelKeys());
        self::assertTrue($repository->findByStore((string) Uuid::uuid7())->isEmpty());
        self::assertTrue($stock->product->is($this->product));
        self::assertTrue($stock->store->is($this->store));
        foreach ([[10, 10, 0], [0, 0, 0], [10, 0, 10]] as [$quantity, $reserved, $available]) {
            $stock->fill(['quantity' => $quantity, 'reserved' => $reserved]);
            $repository->save($stock);
            self::assertSame($quantity, $stock->fresh()->quantity);
            self::assertSame($reserved, $stock->fresh()->reserved);
            self::assertSame($available, $stock->fresh()->available);
        }
        $repository->delete($stock);
        self::assertNull($repository->findByProductAndStore($this->product->id, $this->store->id));
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_reservations_save_order_lookup_store_and_status_filters(): void
    {
        $repository = new StockReservationRepository();
        $order = (string) Uuid::uuid7();
        $ids = [];
        foreach (['reserved', 'issued', 'released', 'returned'] as $status) {
            $product = $this->product($status);
            $reservation = new StockReservation(['order_id' => $order, 'product_id' => $product->id, 'store_id' => $this->store->id, 'quantity' => 2, 'status' => $status]);
            self::assertSame($reservation, $repository->save($reservation));
            $ids[$status] = $reservation->id;
            self::assertSame(2, $repository->findById($reservation->id)->quantity);
            self::assertSame($status, $reservation->fresh()->status);
            self::assertTrue($reservation->product->is($product));
            self::assertTrue($reservation->store->is($this->store));
        }
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create(['store_id' => $otherStore->id, 'name' => 'Other', 'slug' => 'other']);
        $product = $this->product('foreign', ['store_id' => $otherStore->id, 'category_id' => $otherCategory->id]);
        $repository->save(new StockReservation(['order_id' => (string) Uuid::uuid7(), 'product_id' => $product->id, 'store_id' => $otherStore->id, 'quantity' => 1, 'status' => 'reserved']));
        self::assertInstanceOf(Collection::class, $repository->findByOrderId($order));
        self::assertEqualsCanonicalizing(array_values($ids), $repository->findByOrderId($order)->modelKeys());
        self::assertTrue($repository->findByOrderId((string) Uuid::uuid7())->isEmpty());
        self::assertNull($repository->findById((string) Uuid::uuid7()));
        self::assertEqualsCanonicalizing([$ids['reserved'], $ids['issued']], $repository->findActiveByStore($this->store->id)->modelKeys());
        foreach (['reserved', 'issued'] as $status) {
            self::assertSame([$ids[$status]], $repository->findActiveByStore($this->store->id, $status)->modelKeys());
        }
        foreach (['released', 'returned', 'pending'] as $status) {
            self::assertTrue($repository->findActiveByStore($this->store->id, $status)->isEmpty());
        }
        $reservation = $repository->findById($ids['reserved']);
        $reservation->fill(['quantity' => 3, 'status' => 'issued']);
        $repository->save($reservation);
        self::assertSame(3, $repository->findById($reservation->id)->quantity);
        self::assertSame('issued', $reservation->fresh()->status);
        self::assertNull($reservation->fresh()->rental_start_at);
        self::assertNull($reservation->fresh()->issued_at);
    }
}
