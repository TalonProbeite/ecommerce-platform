<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Messaging\EventPublisherInterface;
use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Infrastructure\Storage\S3BucketGuard;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use SplFileInfo;
use Tests\PostgresTestCase;

class ProductCacheIntegrationTest extends PostgresTestCase
{
    private const DISK = 'images';

    private Store $store;
    private Category $category;
    private ProductCache $cache;
    private EventPublisherInterface $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        self::assertInstanceOf(RedisStore::class, Cache::store()->getStore());

        Storage::fake(self::DISK);

        $this->store = Store::create(['name' => 'Tools']);
        $this->category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);

        $this->publisher = Mockery::mock(EventPublisherInterface::class);
        $this->publisher->shouldReceive('publish');

        $this->cache = app(ProductCache::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_product_card_is_served_from_redis_without_queries_on_the_second_read(): void
    {
        $product = $this->createProduct('Drill');
        $service = $this->productService();

        $service->get($product->id, $this->store->id);

        $queries = $this->countQueries(function () use ($service, $product): void {
            $service->get($product->id, $this->store->id);
        });

        self::assertSame(0, $queries);
    }

    public function test_card_round_trips_through_redis_as_a_product_model(): void
    {
        $product = $this->createProduct('Drill');
        $service = $this->productService();

        $service->get($product->id, $this->store->id);
        $cached = $service->get($product->id, $this->store->id);

        self::assertInstanceOf(Product::class, $cached);
        self::assertSame($product->id, $cached->id);
        self::assertSame('Drill', $cached->name);
        self::assertSame($this->store->id, $cached->store_id);
        self::assertTrue($cached->relationLoaded('stock'));
        self::assertTrue($cached->relationLoaded('category'));
        self::assertSame(5, $cached->stock->quantity);
    }

    public function test_updating_product_a_flushes_its_card_but_not_product_b(): void
    {
        $productA = $this->createProduct('Drill A');
        $productB = $this->createProduct('Drill B');
        $service = $this->productService();

        $service->get($productA->id, $this->store->id);
        $service->get($productB->id, $this->store->id);

        $service->update(
            $this->store->id,
            $productA->id,
            new UpdateProductData(name: 'Drill A updated'),
        );

        self::assertGreaterThan(0, $this->countQueries(fn () => $service->get($productA->id, $this->store->id)));
        self::assertSame(0, $this->countQueries(fn () => $service->get($productB->id, $this->store->id)));
    }

    public function test_stock_change_flushes_the_product_card_and_lists_but_not_other_cards(): void
    {
        $productA = $this->createProduct('Drill A', quantity: 10);
        $productB = $this->createProduct('Drill B', quantity: 10);
        $productService = $this->productService();
        $stockService = $this->stockService();
        $filters = new ProductFilters(storeId: $this->store->id);

        $productService->get($productA->id, $this->store->id);
        $productService->get($productB->id, $this->store->id);
        $productService->list($filters);

        $stockService->setQuantity($this->store->id, $productA->id, 7);

        self::assertGreaterThan(0, $this->countQueries(fn () => $productService->get($productA->id, $this->store->id)));
        self::assertSame(0, $this->countQueries(fn () => $productService->get($productB->id, $this->store->id)));
        self::assertGreaterThan(0, $this->countQueries(fn () => $productService->list($filters)));
    }

    public function test_image_upload_flushes_only_the_product_card(): void
    {
        $productA = $this->createProduct('Drill A');
        $productB = $this->createProduct('Drill B');
        $productService = $this->productService();
        $imageService = $this->imageService();

        $productService->get($productA->id, $this->store->id);
        $productService->get($productB->id, $this->store->id);

        $file = $this->pngFile();

        try {
            $imageService->upload($this->store->id, $productA->id, new SplFileInfo($file));
        } finally {
            unlink($file);
        }

        self::assertGreaterThan(0, $this->countQueries(fn () => $productService->get($productA->id, $this->store->id)));
        self::assertSame(0, $this->countQueries(fn () => $productService->get($productB->id, $this->store->id)));
    }

    public function test_category_update_flushes_all_its_product_cards_and_lists(): void
    {
        $otherCategory = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);
        $productA1 = $this->createProduct('Drill A1');
        $productA2 = $this->createProduct('Drill A2');
        $trashedProduct = $this->createProduct('Drill A3');
        $productB = $this->createProduct('Drill B', $otherCategory);
        $trashedProduct->delete();

        $cache = $this->cache;
        $categoryProductIds = [$productA1->id, $productA2->id, $trashedProduct->id];

        foreach ($categoryProductIds as $productId) {
            $cache->rememberCard($productId, fn (): string => "cached-{$productId}");
        }

        $cache->rememberCard($productB->id, fn (): string => "cached-{$productB->id}");
        $cache->rememberList(['store_id' => $this->store->id], 1, 20, fn (): string => 'cached-list');

        $this->category->update(['name' => 'Renamed']);

        foreach ($categoryProductIds as $productId) {
            self::assertSame(
                "refreshed-{$productId}",
                $cache->rememberCard($productId, fn (): string => "refreshed-{$productId}"),
            );
        }

        self::assertSame(
            "cached-{$productB->id}",
            $cache->rememberCard($productB->id, fn (): string => "refreshed-{$productB->id}"),
        );
        self::assertSame(
            'refreshed-list',
            $cache->rememberList(['store_id' => $this->store->id], 1, 20, fn (): string => 'refreshed-list'),
        );
    }

    public function test_list_is_cached_and_product_creation_invalidates_it(): void
    {
        $this->createProduct('Drill A');
        $service = $this->productService();
        $filters = new ProductFilters(storeId: $this->store->id);

        self::assertSame(1, $service->list($filters)->total());
        self::assertSame(0, $this->countQueries(fn () => $service->list($filters)));

        $service->create($this->store->id, new CreateProductData(
            name: 'Drill B',
            slug: 'drill-b',
            categoryId: $this->category->id,
            pricePerHour: 500,
            quantity: 3,
        ));

        $refreshed = null;
        $queries = $this->countQueries(function () use ($service, $filters, &$refreshed): void {
            $refreshed = $service->list($filters);
        });

        self::assertGreaterThan(0, $queries);
        self::assertNotNull($refreshed);
        self::assertSame(2, $refreshed->total());
    }

    public function test_cached_card_rejects_a_different_store(): void
    {
        $product = $this->createProduct('Drill');
        $service = $this->productService();
        $service->get($product->id, $this->store->id);

        $otherStore = Store::create(['name' => 'Other']);

        $this->expectException(ProductNotFoundException::class);

        $service->get($product->id, $otherStore->id);
    }

    private function productService(): ProductService
    {
        return new ProductService(
            new ProductRepository(),
            new ProductAttributeRepository(),
            new CategoryRepository(),
            new StockRepository(),
            new StockReservationRepository(),
            $this->publisher,
            $this->cache,
        );
    }

    private function stockService(): StockService
    {
        return new StockService(
            new StockRepository(),
            $this->publisher,
            $this->cache,
        );
    }

    private function imageService(): ProductImageService
    {
        $guard = new S3BucketGuard();
        (new ReflectionProperty($guard, 'verifiedDisks'))->setValue($guard, [self::DISK => true]);

        return new ProductImageService(
            new ProductImageRepository(),
            new ProductRepository(),
            $guard,
            self::DISK,
            10,
            ['image/png' => 'png'],
            $this->cache,
        );
    }

    private function createProduct(
        string $name,
        ?Category $category = null,
        int $quantity = 5,
    ): Product {
        $category ??= $this->category;

        $product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . Uuid::uuid7(),
            'price_per_hour' => 500,
        ]);

        Stock::create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => $quantity,
            'reserved' => 0,
        ]);

        return $product;
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function pngFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-image-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        return $path;
    }
}
