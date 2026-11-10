<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Messaging\EventPublisherInterface;
use App\DTO\CreateCategoryData;
use App\Infrastructure\Cache\CategoryCache;
use App\Infrastructure\Cache\ProductCache;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\CategoryService;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class CategoryCacheIntegrationTest extends PostgresTestCase
{
    private Store $storeA;
    private Store $storeB;
    private CategoryCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        self::assertInstanceOf(RedisStore::class, Cache::store()->getStore());

        $publisher = Mockery::mock(EventPublisherInterface::class);
        $publisher->shouldReceive('publish');
        $this->app->instance(EventPublisherInterface::class, $publisher);

        $this->storeA = Store::create(['name' => 'Store A']);
        $this->storeB = Store::create(['name' => 'Store B']);

        $this->cache = app(CategoryCache::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_tree_is_cached_and_the_second_read_does_not_query_the_database(): void
    {
        Category::create([
            'store_id' => $this->storeA->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);

        $service = app(CategoryService::class);

        self::assertCount(1, $service->list($this->storeA->id));

        $queries = $this->countQueries(fn () => $service->list($this->storeA->id));

        self::assertSame(0, $queries);
    }

    public function test_create_in_store_a_invalidates_only_store_a_tree(): void
    {
        $service = app(CategoryService::class);

        $this->cache->rememberTree($this->storeA->id, fn (): string => 'store-a');
        $this->cache->rememberTree($this->storeB->id, fn (): string => 'store-b');

        $service->create($this->storeA->id, new CreateCategoryData(name: 'New', slug: 'new'));

        self::assertSame(
            'store-a-refreshed',
            $this->cache->rememberTree($this->storeA->id, fn (): string => 'store-a-refreshed'),
        );
        self::assertSame(
            'store-b',
            $this->cache->rememberTree($this->storeB->id, fn (): string => 'store-b-refreshed'),
        );
    }

    public function test_updating_category_invalidates_its_store_tree_and_product_cards(): void
    {
        $category = Category::create([
            'store_id' => $this->storeA->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $product = $this->createProduct($category);

        $productCache = app(ProductCache::class);
        $productCache->rememberCard('admin', $product->id, fn (): string => 'card');
        $this->cache->rememberTree($this->storeA->id, fn (): string => 'tree');

        $category->update(['name' => 'Renamed']);

        self::assertSame(
            'tree-refreshed',
            $this->cache->rememberTree($this->storeA->id, fn (): string => 'tree-refreshed'),
        );
        self::assertSame(
            'card-refreshed',
            $productCache->rememberCard('admin', $product->id, fn (): string => 'card-refreshed'),
        );
    }

    public function test_evicted_tree_version_returns_fresh_data(): void
    {
        $this->cache->rememberTree($this->storeA->id, fn (): string => 'initial');

        Cache::forget('categories:ver:store:' . $this->storeA->id);

        $calls = 0;
        $fresh = $this->cache->rememberTree($this->storeA->id, function () use (&$calls): string {
            $calls++;

            return 'fresh';
        });

        self::assertSame('fresh', $fresh);
        self::assertSame(1, $calls);
    }

    private function createProduct(Category $category): Product
    {
        return Product::create([
            'store_id' => $category->store_id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill-' . Uuid::uuid7(),
            'price_per_hour' => 500,
        ]);
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
}
