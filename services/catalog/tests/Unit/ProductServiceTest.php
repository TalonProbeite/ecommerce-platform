<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductAttributeRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\DTO\CreateProductData;
use App\DTO\ProductAttributeData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
use App\Exceptions\ProductCategoryNotFoundException;
use App\Exceptions\ProductHasActiveReservationsException;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Services\ProductService;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        DB::shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());
        DB::shouldReceive('afterCommit')
            ->andReturnUsing(fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_list_delegates_to_repository_and_returns_a_paginator(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $product = $this->product();
        $filters = new ProductFilters(storeId: 'store-123');
        $paginator = new Paginator([$product], 1, $filters->limit, $filters->page);

        $productRepository->shouldReceive('findByFilters')
            ->once()
            ->with($filters)
            ->andReturn($paginator);

        $service = $this->service($productRepository);
        $result = $service->list($filters);

        self::assertSame([$product], $result->items());
        self::assertSame(1, $result->total());
    }

    public function test_get_returns_detailed_product_scoped_to_store(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $product = $this->product();

        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);

        $service = $this->service($productRepository);
        self::assertSame($product, $service->get('product-123', 'store-123'));
    }

    public function test_get_throws_when_product_is_missing_or_belongs_to_another_store(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'other-store')
            ->andReturn(null);

        $this->expectException(ProductNotFoundException::class);

        $this->service($productRepository)->get('product-123', 'other-store');
    }

    public function test_get_caches_the_detailed_product(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $product = $this->product();
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);

        $service = $this->service($productRepository);

        self::assertSame($product, $service->get('product-123', 'store-123'));
        self::assertSame($product, $service->get('product-123', 'store-123'));
    }

    public function test_get_rejects_a_cached_product_from_another_store(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $product = $this->product();
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);

        $service = $this->service($productRepository);
        $service->get('product-123', 'store-123');

        $this->expectException(ProductNotFoundException::class);

        $service->get('product-123', 'other-store');
    }

    public function test_list_caches_items_and_total(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $product = $this->product();
        $filters = new ProductFilters(storeId: 'store-123', isAvailable: null);
        $paginator = new Paginator([$product], 1, $filters->limit, $filters->page);

        $productRepository->shouldReceive('findByFilters')
            ->once()
            ->with($filters)
            ->andReturn($paginator);

        $service = $this->service($productRepository);
        $first = $service->list($filters);
        $second = $service->list($filters);

        self::assertSame(1, $first->total());
        self::assertSame($filters->page, $first->currentPage());
        self::assertSame($filters->limit, $first->perPage());
        self::assertSame([$product], $first->items());
        self::assertSame($first->items(), $second->items());
    }

    public function test_list_availability_filters_bypass_the_cache(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $filters = new ProductFilters(storeId: 'store-123', isAvailable: true);
        $paginator = new Paginator([$this->product()], 1, $filters->limit, $filters->page);

        $productRepository->shouldReceive('findByFilters')
            ->twice()
            ->with($filters)
            ->andReturn($paginator);

        $service = $this->service($productRepository);

        self::assertSame($paginator->items(), $service->list($filters)->items());
        self::assertSame($paginator->items(), $service->list($filters)->items());
    }

    public function test_update_flushes_only_the_affected_product_card_and_lists(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $cache = $this->cache();
        $product = $this->product();
        $data = new UpdateProductData(name: 'Updated Drill');

        $cache->rememberCard('product-123', fn (): string => 'old-card');
        $cache->rememberCard('product-999', fn (): string => 'other-card');
        $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'old-list');

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $productRepository->shouldReceive('updateProduct')
            ->once()
            ->with('product-123', 'store-123', $data)
            ->andReturn($product);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $publisher->shouldReceive('publish')->once()->with(Mockery::type(ProductUpdated::class));

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $cache,
        );

        $service->update('store-123', 'product-123', $data);

        self::assertSame('new-card', $cache->rememberCard('product-123', fn (): string => 'new-card'));
        self::assertSame('other-card', $cache->rememberCard('product-999', fn (): string => 'refreshed-other-card'));
        self::assertSame('new-list', $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'new-list'));
    }

    public function test_create_persists_product_stock_and_attributes_and_publishes_event(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $category = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $category->id = 'category-123';
        $product = $this->product();
        $data = new CreateProductData(
            name: 'Drill',
            slug: 'drill',
            categoryId: 'category-123',
            pricePerHour: 500,
            quantity: 7,
            attributes: [new ProductAttributeData('Power', '800 W')],
        );

        $categoryRepository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('category-123', 'store-123')
            ->andReturn($category);
        $productRepository->shouldReceive('createProduct')
            ->once()
            ->with('store-123', $data)
            ->andReturn($product);
        $stockRepository->shouldReceive('createStock')
            ->once()
            ->with('product-123', 'store-123', 7)
            ->andReturn(new Stock(['quantity' => 7, 'reserved' => 0]));
        $attributeRepository->shouldReceive('createProductAttribute')
            ->once()
            ->with($data->attributes, 'product-123');
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(fn (ProductCreated $event): bool => $event->productId === 'product-123'
                && $event->storeId === 'store-123'
                && $event->name === 'Drill'
                && $event->pricePerHour === 500);

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        self::assertSame($product, $service->create('store-123', $data));
    }

    public function test_create_rejects_category_from_another_store(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $categoryRepository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('category-123', 'store-123')
            ->andReturn(null);
        $productRepository->shouldNotReceive('createProduct');
        $stockRepository->shouldNotReceive('createStock');
        $attributeRepository->shouldNotReceive('createProductAttribute');
        $publisher->shouldNotReceive('publish');

        $this->expectException(ProductCategoryNotFoundException::class);

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        $service->create('store-123', new CreateProductData(
            name: 'Drill',
            slug: 'drill',
            categoryId: 'category-123',
            pricePerHour: 500,
        ));
    }

    public function test_update_leaves_attributes_unchanged_when_field_is_absent(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $product = $this->product();
        $data = new UpdateProductData(name: 'Updated Drill');

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $productRepository->shouldReceive('updateProduct')
            ->once()
            ->with('product-123', 'store-123', $data)
            ->andReturn($product);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $attributeRepository->shouldNotReceive('deleteAllByProductId');
        $attributeRepository->shouldNotReceive('createProductAttribute');
        $categoryRepository->shouldNotReceive('findByStoreAndID');
        $publisher->shouldReceive('publish')->once()->with(Mockery::type(ProductUpdated::class));

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        self::assertSame($product, $service->update('store-123', 'product-123', $data));
    }

    public function test_update_replaces_attributes_when_non_empty_array_is_provided(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $product = $this->product();
        $attributes = [new ProductAttributeData('Color', 'Red')];
        $data = new UpdateProductData(attributes: $attributes);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $productRepository->shouldReceive('updateProduct')
            ->once()
            ->with('product-123', 'store-123', $data)
            ->andReturn($product);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $attributeRepository->shouldReceive('deleteAllByProductId')
            ->once()
            ->with('product-123');
        $attributeRepository->shouldReceive('createProductAttribute')
            ->once()
            ->with($attributes, 'product-123');
        $publisher->shouldReceive('publish')->once()->with(Mockery::type(ProductUpdated::class));

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        self::assertSame($product, $service->update('store-123', 'product-123', $data));
    }

    public function test_update_clears_all_attributes_when_empty_array_is_provided(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $product = $this->product();
        $data = new UpdateProductData(attributes: []);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $productRepository->shouldReceive('updateProduct')
            ->once()
            ->with('product-123', 'store-123', $data)
            ->andReturn($product);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $attributeRepository->shouldReceive('deleteAllByProductId')
            ->once()
            ->with('product-123');
        $attributeRepository->shouldNotReceive('createProductAttribute');
        $publisher->shouldReceive('publish')->once()->with(Mockery::type(ProductUpdated::class));

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        self::assertSame($product, $service->update('store-123', 'product-123', $data));
    }

    public function test_update_rejects_explicit_null_for_non_nullable_fields(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($this->product());
        $productRepository->shouldNotReceive('updateProduct');
        $attributeRepository->shouldNotReceive('deleteAllByProductId');
        $publisher->shouldNotReceive('publish');

        $this->expectException(ValidationException::class);

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        $service->update(
            'store-123',
            'product-123',
            new UpdateProductData(name: null, nameProvided: true),
        );
    }

    public function test_update_rejects_category_from_another_store(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($this->product());
        $categoryRepository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('category-999', 'store-123')
            ->andReturn(null);
        $productRepository->shouldNotReceive('updateProduct');
        $publisher->shouldNotReceive('publish');

        $this->expectException(ProductCategoryNotFoundException::class);

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        $service->update(
            'store-123',
            'product-123',
            new UpdateProductData(categoryId: 'category-999'),
        );
    }

    public function test_delete_locks_stock_and_rejects_active_reservations(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($this->product());
        $stockRepository->shouldReceive('lockByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn(new Stock(['quantity' => 5, 'reserved' => 1]));
        $reservationRepository->shouldReceive('hasActiveByProduct')
            ->once()
            ->with('product-123')
            ->andReturn(true);
        $productRepository->shouldNotReceive('deleteProduct');
        $publisher->shouldNotReceive('publish');

        $this->expectException(ProductHasActiveReservationsException::class);

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            $reservationRepository,
            $publisher,
            $this->cache(),
        );

        $service->delete('store-123', 'product-123');
    }

    public function test_delete_soft_deletes_product_and_publishes_event(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($this->product());
        $stockRepository->shouldReceive('lockByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn(new Stock(['quantity' => 5, 'reserved' => 0]));
        $reservationRepository->shouldReceive('hasActiveByProduct')
            ->once()
            ->with('product-123')
            ->andReturn(false);
        $productRepository->shouldReceive('deleteProduct')
            ->once()
            ->with('product-123', 'store-123');
        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(fn (ProductDeleted $event): bool => $event->productId === 'product-123'
                && $event->storeId === 'store-123');

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            $reservationRepository,
            $publisher,
            $this->cache(),
        );

        $service->delete('store-123', 'product-123');

        $this->addToAssertionCount(1);
    }

    public function test_restore_is_store_scoped_and_publishes_updated_event(): void
    {
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $attributeRepository = Mockery::mock(ProductAttributeRepositoryInterface::class);
        $categoryRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $stockRepository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $product = $this->product();

        $productRepository->shouldReceive('restoreProduct')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $productRepository->shouldReceive('findDetailedById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($product);
        $publisher->shouldReceive('publish')
            ->once()
            ->with(Mockery::type(ProductUpdated::class));

        $service = new ProductService(
            $productRepository,
            $attributeRepository,
            $categoryRepository,
            $stockRepository,
            Mockery::mock(StockReservationRepositoryInterface::class),
            $publisher,
            $this->cache(),
        );

        self::assertSame($product, $service->restore('store-123', 'product-123'));
    }

    private function service(
        ProductRepositoryInterface $productRepository,
    ): ProductService {
        return new ProductService(
            $productRepository,
            Mockery::mock(ProductAttributeRepositoryInterface::class),
            Mockery::mock(CategoryRepositoryInterface::class),
            Mockery::mock(StockRepositoryInterface::class),
            Mockery::mock(StockReservationRepositoryInterface::class),
            Mockery::mock(EventPublisherInterface::class),
            $this->cache(),
        );
    }

    private function cache(?ProductRepositoryInterface $productRepository = null): ProductCache
    {
        return new ProductCache(
            300,
            600,
            $productRepository ?? Mockery::mock(ProductRepositoryInterface::class),
        );
    }

    private function product(): Product
    {
        $product = new Product([
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 500,
            'store_id' => 'store-123',
            'category_id' => 'category-123',
        ]);
        $product->id = 'product-123';

        return $product;
    }
}
