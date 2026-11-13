<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\CreateCategoryData;
use App\DTO\CreateProductData;
use App\DTO\CreateStoreData;
use App\DTO\ProductAttributeData;
use App\DTO\ProductFilters;
use App\DTO\StoreFilters;
use App\DTO\UpdateCategoryData;
use App\DTO\UpdateProductAttribute;
use App\DTO\UpdateProductData;
use App\DTO\UpdateStoreData;
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
use App\Repositories\ProductAttributeRepository;
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

    public function test_stock_reservation_has_active_by_store_detection(): void
    {
        $repository = new StockReservationRepository();
        self::assertFalse($repository->hasActiveByStore($this->store->id));

        StockReservation::create([
            'order_id' => (string) Uuid::uuid7(),
            'order_line_id' => (string) Uuid::uuid7(),
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        self::assertTrue($repository->hasActiveByStore($this->store->id));
    }

    public function test_categories_scope_tree_save_update_and_soft_delete(): void
    {
        $repository = new CategoryRepository();
        $child = $repository->createCategory(
            new CreateCategoryData(name: 'Child', slug: 'child', parentId: $this->category->id),
            $this->store->id,
        );
        self::assertInstanceOf(Category::class, $child);
        self::assertSame('Child', $child->name);
        self::assertSame($this->store->id, $child->store_id);
        self::assertSame($this->category->id, $child->parent_id);

        $other = Store::create(['name' => 'Other']);
        Category::create(['store_id' => $other->id, 'name' => 'Other', 'slug' => 'tools']);

        self::assertInstanceOf(Collection::class, $repository->findByStore($this->store->id));
        // findByStore returns root categories with recursiveChildren loaded
        self::assertSame([$this->category->id], $repository->findByStore($this->store->id)->modelKeys());
        self::assertTrue($repository->findByStore($this->store->id)->first()->recursiveChildren->contains($child));

        self::assertSame([$this->category->id], $repository->findRoots($this->store->id)->modelKeys());
        self::assertSame([$child->id], $repository->findChildren($this->category->id, $this->store->id)->modelKeys());
        self::assertTrue($repository->findChildren($this->category->id, $other->id)->isEmpty());

        self::assertTrue($repository->hasSubcategories($this->category->id, $this->store->id));

        $foundByStoreAndId = $repository->findByStoreAndID($child->id, $this->store->id);
        self::assertNotNull($foundByStoreAndId);
        self::assertSame($child->id, $foundByStoreAndId->id);
        self::assertNull($repository->findByStoreAndID($child->id, $other->id));

        self::assertTrue($child->parent->is($this->category));

        $updated = $repository->updateCategory($child->id, $this->store->id, new UpdateCategoryData(name: 'Updated'));
        self::assertSame('Updated', $updated->name);
        self::assertSame('Updated', $repository->findByStoreAndID($child->id, $this->store->id)->name);

        $repository->deleteCategory($child->id, $this->store->id);
        self::assertNull($repository->findByStoreAndID($child->id, $this->store->id));
        self::assertTrue(Category::withTrashed()->findOrFail($child->id)->trashed());
        self::assertTrue($repository->findChildren($this->category->id, $this->store->id)->isEmpty());
    }

    public function test_products_create_scope_update_soft_delete_and_restore(): void
    {
        $repository = new ProductRepository();
        $product = $repository->createProduct(
            $this->store->id,
            new CreateProductData(
                name: 'Saw',
                slug: 'saw',
                categoryId: $this->category->id,
                pricePerHour: 300,
            ),
        );

        self::assertSame($this->store->id, $product->store_id);
        self::assertTrue($repository->findById($product->id)->is($product));
        self::assertTrue($repository->findById($product->id, $this->store->id)->is($product));

        $otherStore = (string) Uuid::uuid7();
        self::assertNull($repository->findById($product->id, $otherStore));
        self::assertNull($repository->findDetailedById($product->id, $otherStore));

        $updated = $repository->updateProduct(
            $product->id,
            $this->store->id,
            new UpdateProductData(pricePerHour: 450),
        );
        self::assertSame(450, $updated->price_per_hour);
        self::assertSame(450, $repository->findById($product->id)->price_per_hour);

        self::assertNull($repository->updateProduct(
            $product->id,
            $otherStore,
            new UpdateProductData(pricePerHour: 999),
        ));
        self::assertSame(450, $repository->findById($product->id)->price_per_hour);

        $repository->deleteProduct($product->id, $this->store->id);
        self::assertNull($repository->findById($product->id));
        self::assertNull($repository->findDetailedById($product->id));
        self::assertTrue(Product::withTrashed()->findOrFail($product->id)->trashed());

        $restored = $repository->restoreProduct($product->id, $this->store->id);
        self::assertFalse($restored->trashed());
        self::assertTrue($repository->findById($product->id)->is($restored));
    }

    public function test_product_filters_sorting_and_pagination(): void
    {
        $repository = new ProductRepository();
        $low = $this->product('low', ['price_per_hour' => 200, 'deposit_amount' => 500]);
        $high = $this->product('high', ['price_per_hour' => 300, 'deposit_amount' => 500]);
        $this->product('inactive', ['is_active' => false, 'price_per_hour' => 200]);
        $this->product('deposit', ['price_per_hour' => 200, 'deposit_amount' => 501]);
        $this->product('expensive', ['price_per_hour' => 301]);
        $otherCategory = Category::create(['store_id' => $this->store->id, 'name' => 'Other', 'slug' => 'other']);
        $this->product('other-category', ['category_id' => $otherCategory->id, 'price_per_hour' => 200]);
        $otherStore = Store::create(['name' => 'Other']);
        $category = Category::create(['store_id' => $otherStore->id, 'name' => 'Tools', 'slug' => 'tools']);
        $this->product('other-store', ['store_id' => $otherStore->id, 'category_id' => $category->id, 'price_per_hour' => 200]);
        $filters = new ProductFilters(
            storeId: $this->store->id,
            categoryId: $this->category->id,
            minPrice: 200,
            maxPrice: 300,
            maxDeposit: 500,
            isActive: true,
            isAvailable: null,
            sortBy: ProductSortField::PRICE,
            sortDirection: SortDirection::DESC,
            limit: 1,
        );
        $page = $repository->findByFilters($filters);
        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertSame(2, $page->total());
        self::assertSame([$high->id], $page->getCollection()->modelKeys());
        $second = $repository->findByFilters(new ProductFilters(...array_replace(get_object_vars($filters), ['page' => 2])));
        self::assertSame([$low->id], $second->getCollection()->modelKeys());
        self::assertSame(2, $second->currentPage());
        self::assertSame(1, $repository->findByFilters(new ProductFilters(isActive: false, isAvailable: null))->total());
        self::assertSame(8, $repository->findByFilters(new ProductFilters(isActive: null, isAvailable: null))->total());
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
            $page = $repository->findByFilters(new ProductFilters(storeId: $this->store->id, isAvailable: null, sortBy: ProductSortField::PRICE, sortDirection: $direction, limit: 1));
            self::assertSame(2, $page->total());
            self::assertSame([$expected[0]], $page->getCollection()->modelKeys());
            $second = $repository->findByFilters(new ProductFilters(storeId: $this->store->id, isAvailable: null, sortBy: ProductSortField::PRICE, sortDirection: $direction, limit: 1, page: 2));
            self::assertSame([$expected[1]], $second->getCollection()->modelKeys());
        }
        self::assertSame([$foreign->id], $repository->findByFilters(new ProductFilters(storeId: $otherStore->id, isAvailable: null))->getCollection()->modelKeys());
        $repository->deleteProduct($samePrice->id, $this->store->id);
        self::assertSame(1, $repository->findByFilters(new ProductFilters(storeId: $this->store->id, isAvailable: null))->total());
        self::assertTrue($repository->findByFilters(new ProductFilters(storeId: (string) Uuid::uuid7(), isAvailable: null))->isEmpty());
    }

    public function test_product_availability_filter_uses_stock_balance(): void
    {
        $repository = new ProductRepository();
        $available = $this->product('available');
        $reserved = $this->product('reserved');
        $unstocked = $this->product('unstocked');

        Stock::create(['product_id' => $available->id, 'store_id' => $this->store->id, 'quantity' => 3, 'reserved' => 1]);
        Stock::create(['product_id' => $reserved->id, 'store_id' => $this->store->id, 'quantity' => 2, 'reserved' => 2]);

        self::assertSame(
            [$available->id],
            $repository->findByFilters(new ProductFilters(storeId: $this->store->id, isAvailable: true))->getCollection()->modelKeys(),
        );
        self::assertEqualsCanonicalizing(
            [$this->product->id, $reserved->id, $unstocked->id],
            $repository->findByFilters(new ProductFilters(storeId: $this->store->id, isAvailable: false))->getCollection()->modelKeys(),
        );
    }

    public function test_detailed_product_eager_loads_exact_related_data(): void
    {
        ProductAttribute::create(['product_id' => $this->product->id, 'name' => 'Power', 'value' => '800 W']);
        ProductImage::create(['product_id' => $this->product->id, 'object_key' => 'first.jpg', 'is_main' => true]);
        ProductImage::create(['product_id' => $this->product->id, 'object_key' => 'second.jpg']);
        Stock::create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'quantity' => 10, 'reserved' => 3]);
        $product = (new ProductRepository())->findDetailedById($this->product->id, $this->store->id);
        foreach (['category', 'attributes', 'images', 'mainImage', 'stock'] as $relation) {
            self::assertTrue($product->relationLoaded($relation));
        }
        self::assertTrue($product->category->is($this->category));
        self::assertSame(['800 W'], $product->attributes->pluck('value')->all());
        self::assertSame(['first.jpg', 'second.jpg'], $product->images->pluck('object_key')->all());
        self::assertSame('first.jpg', $product->mainImage?->object_key);
        self::assertSame(7, $product->stock->available);
        self::assertSame(100, $product->price_per_hour);
        self::assertTrue($product->is_active);
    }

    public function test_images_create_order_scope_update_main_flag_and_hard_delete(): void
    {
        $repository = new ProductImageRepository();
        $other = $this->product('other');
        $first = $repository->create([
            'product_id' => $this->product->id,
            'object_key' => 'front.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'is_main' => true,
        ]);
        $second = $repository->create([
            'product_id' => $this->product->id,
            'object_key' => 'back.jpg',
        ]);

        self::assertSame([$first->id, $second->id], $repository->listForProduct($this->product->id)->modelKeys());
        self::assertNull($repository->findByIdForProduct($second->id, $other->id));
        self::assertTrue($repository->listForProduct($other->id)->isEmpty());
        self::assertTrue($first->product->is($this->product));
        self::assertSame($first->id, $repository->firstForProduct($this->product->id)->id);

        $updated = $repository->update($second, [
            'object_key' => 'updated.jpg',
            'mime_type' => 'image/png',
            'size' => 2048,
        ]);
        self::assertSame('updated.jpg', $updated->object_key);
        self::assertSame('image/png', $updated->mime_type);
        self::assertSame(2048, $updated->size);
        self::assertFalse($updated->is_main);

        $repository->clearMainForProduct($this->product->id);
        self::assertFalse($first->fresh()->is_main);
        self::assertFalse($second->fresh()->is_main);

        $repository->delete($updated);
        self::assertNull($repository->findByIdForProduct($second->id, $this->product->id));
        $this->assertDatabaseMissing('product_images', ['id' => $second->id]);
    }

    public function test_product_attribute_repository_crud_and_bulk_delete(): void
    {
        $repository = new ProductAttributeRepository();
        $other = $this->product('other');

        $repository->createProductAttribute([
            new ProductAttributeData('Power', '800 W'),
            new ProductAttributeData('Color', 'Black'),
        ], $this->product->id);
        $repository->createProductAttribute([
            new ProductAttributeData('Power', '500 W'),
        ], $other->id);

        self::assertCount(2, $repository->findAllByProductId($this->product->id));
        $power = $repository->findByNameAndProductId($this->product->id, 'Power');
        self::assertNotNull($power);
        self::assertSame('800 W', $power->value);
        self::assertNull($repository->findByNameAndProductId($other->id, 'Color'));

        $updated = $repository->updateProductAttribute($power->id, new UpdateProductAttribute(value: '1200 W'));
        self::assertSame('1200 W', $updated->value);
        self::assertSame('1200 W', $repository->findByNameAndProductId($this->product->id, 'Power')->value);

        $repository->deleteProductAttribute($updated->id);
        self::assertNull($repository->findByNameAndProductId($this->product->id, 'Power'));
        self::assertCount(1, $repository->findAllByProductId($this->product->id));

        $repository->deleteAllByProductId($this->product->id);
        self::assertTrue($repository->findAllByProductId($this->product->id)->isEmpty());
        self::assertCount(1, $repository->findAllByProductId($other->id));
    }

    public function test_stock_create_isolation_locking_and_conditional_updates(): void
    {
        $repository = new StockRepository();
        $stock = $repository->createStock($this->product->id, $this->store->id, 10);
        self::assertSame(10, $stock->quantity);
        self::assertSame(0, $stock->reserved);
        self::assertSame(10, $stock->available);

        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create(['store_id' => $otherStore->id, 'name' => 'Tools', 'slug' => 'tools']);
        $otherProduct = $this->product('foreign-stock', ['store_id' => $otherStore->id, 'category_id' => $otherCategory->id]);
        $otherStock = $repository->createStock($otherProduct->id, $otherStore->id, 5);

        self::assertTrue($repository->findByProductId($this->product->id, $this->store->id)->is($stock));
        self::assertNull($repository->findByProductId($this->product->id, $otherStore->id));
        self::assertNull($repository->findByProductId((string) Uuid::uuid7(), $this->store->id));
        self::assertTrue($repository->lockByProductId($this->product->id, $this->store->id)->is($stock));
        self::assertNull($repository->lockByProductId($this->product->id, $otherStore->id));
        self::assertTrue($otherStock->product->is($otherProduct));
        self::assertTrue($stock->product->is($this->product));
        self::assertTrue($stock->store->is($this->store));

        self::assertSame(1, $repository->setQuantityIfNotBelowReserved($stock->id, 15));
        self::assertSame(15, $stock->fresh()->quantity);

        $repository->adjustReserved($stock->id, 3);
        self::assertSame(3, $stock->fresh()->reserved);
        self::assertSame(12, $stock->fresh()->available);
        self::assertSame(0, $repository->adjustReserved($stock->id, 20));
        self::assertSame(3, $stock->fresh()->reserved);
        self::assertSame(0, $repository->adjustReserved($stock->id, -5));
        self::assertSame(3, $stock->fresh()->reserved);
        self::assertSame(0, $repository->setQuantityIfNotBelowReserved($stock->id, 2));
        self::assertSame(15, $stock->fresh()->quantity);
        self::assertSame(1, $repository->setQuantityIfNotBelowReserved($stock->id, 3));
        self::assertSame(3, $stock->fresh()->quantity);
    }

    public function test_reservations_save_order_lookup_store_and_status_filters(): void
    {
        $repository = new StockReservationRepository();
        $order = (string) Uuid::uuid7();
        $ids = [];
        $productIds = [];
        foreach (['reserved', 'issued', 'released', 'returned'] as $status) {
            $product = $this->product($status);
            $reservation = new StockReservation(['order_id' => $order, 'order_line_id' => (string) Uuid::uuid7(), 'product_id' => $product->id, 'store_id' => $this->store->id, 'quantity' => 2, 'status' => $status]);
            self::assertSame($reservation, $repository->save($reservation));
            $ids[$status] = $reservation->id;
            $productIds[$status] = $product->id;
            self::assertSame(2, $repository->findById($reservation->id)->quantity);
            self::assertSame($status, $reservation->fresh()->status);
            self::assertTrue($reservation->product->is($product));
            self::assertTrue($reservation->store->is($this->store));
        }
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create(['store_id' => $otherStore->id, 'name' => 'Other', 'slug' => 'other']);
        $product = $this->product('foreign', ['store_id' => $otherStore->id, 'category_id' => $otherCategory->id]);
        $repository->save(new StockReservation(['order_id' => (string) Uuid::uuid7(), 'order_line_id' => (string) Uuid::uuid7(), 'product_id' => $product->id, 'store_id' => $otherStore->id, 'quantity' => 1, 'status' => 'reserved']));
        self::assertInstanceOf(Collection::class, $repository->findByOrderId($order));
        self::assertEqualsCanonicalizing(array_values($ids), $repository->findByOrderId($order)->modelKeys());
        self::assertTrue($repository->findByOrderId((string) Uuid::uuid7())->isEmpty());
        self::assertNull($repository->findById((string) Uuid::uuid7()));
        self::assertEqualsCanonicalizing([$ids['reserved'], $ids['issued']], $repository->findActiveByStore($this->store->id)->modelKeys());
        self::assertTrue($repository->hasActiveByProduct($productIds['reserved']));
        self::assertTrue($repository->hasActiveByProduct($productIds['issued']));
        self::assertFalse($repository->hasActiveByProduct($productIds['returned']));
        self::assertFalse($repository->hasActiveByProduct((string) Uuid::uuid7()));
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
