<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Cache\ProductCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ProductCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_remember_card_and_list_return_cached_values_without_calling_callbacks_again(): void
    {
        $cache = $this->cache();
        $cardCalls = 0;
        $listCalls = 0;

        $cardCallback = function () use (&$cardCalls): array {
            $cardCalls++;

            return ['id' => 'product-123'];
        };
        $listCallback = function () use (&$listCalls): array {
            $listCalls++;

            return ['items' => [], 'total' => 0];
        };

        self::assertSame(['id' => 'product-123'], $cache->rememberCard('product-123', $cardCallback));
        self::assertSame(['id' => 'product-123'], $cache->rememberCard('product-123', $cardCallback));
        self::assertSame(['items' => [], 'total' => 0], $cache->rememberList(['store_id' => 'store-1'], 1, 20, $listCallback));
        self::assertSame(['items' => [], 'total' => 0], $cache->rememberList(['store_id' => 'store-1'], 1, 20, $listCallback));

        self::assertSame(1, $cardCalls);
        self::assertSame(1, $listCalls);
    }

    public function test_invalidate_card_only_resets_the_requested_card(): void
    {
        $cache = $this->cache();
        $firstCalls = 0;
        $secondCalls = 0;

        $cache->rememberCard('product-1', function () use (&$firstCalls): string {
            $firstCalls++;

            return 'first';
        });
        $cache->rememberCard('product-2', function () use (&$secondCalls): string {
            $secondCalls++;

            return 'second';
        });

        $cache->invalidateCard('product-1');

        self::assertSame('first-refreshed', $cache->rememberCard('product-1', function () use (&$firstCalls): string {
            $firstCalls++;

            return 'first-refreshed';
        }));
        self::assertSame('second', $cache->rememberCard('product-2', function () use (&$secondCalls): string {
            $secondCalls++;

            return 'second-refreshed';
        }));

        self::assertSame(2, $firstCalls);
        self::assertSame(1, $secondCalls);
    }

    public function test_invalidate_lists_only_resets_lists(): void
    {
        $cache = $this->cache();
        $listCalls = 0;
        $cardCalls = 0;

        $cache->rememberList(['store_id' => 'store-1'], 1, 20, function () use (&$listCalls): string {
            $listCalls++;

            return 'list';
        });
        $cache->rememberCard('product-1', function () use (&$cardCalls): string {
            $cardCalls++;

            return 'card';
        });

        $cache->invalidateLists();

        self::assertSame('list-refreshed', $cache->rememberList(['store_id' => 'store-1'], 1, 20, function () use (&$listCalls): string {
            $listCalls++;

            return 'list-refreshed';
        }));
        self::assertSame('card', $cache->rememberCard('product-1', function () use (&$cardCalls): string {
            $cardCalls++;

            return 'card-refreshed';
        }));

        self::assertSame(2, $listCalls);
        self::assertSame(1, $cardCalls);
    }

    public function test_invalidate_category_resets_all_category_products_and_lists_only(): void
    {
        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('findIdsByCategory')
            ->once()
            ->with('category-1')
            ->andReturn(['product-1', 'product-2']);

        $cache = $this->cache($repository);

        $cache->rememberCard('product-1', fn (): string => 'cached-product-1');
        $cache->rememberCard('product-2', fn (): string => 'cached-product-2');
        $cache->rememberCard('product-3', fn (): string => 'cached-product-3');
        $cache->rememberList([], 1, 20, fn (): string => 'cached-list');

        $cache->invalidateCategory('category-1');

        self::assertSame('refreshed-product-1', $cache->rememberCard('product-1', fn (): string => 'refreshed-product-1'));
        self::assertSame('refreshed-product-2', $cache->rememberCard('product-2', fn (): string => 'refreshed-product-2'));
        self::assertSame('cached-product-3', $cache->rememberCard('product-3', fn (): string => 'refreshed-product-3'));
        self::assertSame('refreshed-list', $cache->rememberList([], 1, 20, fn (): string => 'refreshed-list'));
    }

    public function test_list_key_normalizes_nested_filter_order(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $callback = function () use (&$calls): string {
            $calls++;

            return 'list';
        };

        $cache->rememberList(['b' => 2, 'a' => ['z' => 1, 'y' => 2]], 1, 20, $callback);
        $cache->rememberList(['a' => ['y' => 2, 'z' => 1], 'b' => 2], 1, 20, $callback);

        self::assertSame(1, $calls);
    }

    public function test_list_and_card_use_their_configured_ttl(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');

        $cache = $this->cache(listTtl: 1, cardTtl: 10);
        $cache->rememberList([], 1, 20, fn (): string => 'list');
        $cache->rememberCard('product-123', fn (): string => 'card');

        Carbon::setTestNow('2026-01-01 00:00:02');

        self::assertSame('fresh list', $cache->rememberList([], 1, 20, fn (): string => 'fresh list'));
        self::assertSame('card', $cache->rememberCard('product-123', fn (): string => 'fresh card'));
    }

    public function test_late_writer_to_the_old_card_key_cannot_overwrite_the_fresh_card(): void
    {
        $cache = $this->cache();

        $cache->rememberCard('product-1', fn (): string => 'initial');

        $oldKey = 'products:card:product-1:v' . (int) Cache::get('products:ver:card:product-1');

        $cache->invalidateCard('product-1');
        Cache::put($oldKey, 'stale', 600);

        $calls = 0;
        $fresh = $cache->rememberCard('product-1', function () use (&$calls): string {
            $calls++;

            return 'fresh';
        });

        self::assertSame('fresh', $fresh);
        self::assertSame(1, $calls);
        self::assertSame('stale', Cache::get($oldKey));
    }

    public function test_late_writer_to_the_old_list_key_cannot_overwrite_the_fresh_list(): void
    {
        $cache = $this->cache();
        $filters = ['store_id' => 'store-1'];

        $cache->rememberList($filters, 1, 20, fn (): string => 'initial');

        $hash = sha1((string) json_encode(
            ['filters' => $filters, 'page' => 1, 'per_page' => 20],
            JSON_THROW_ON_ERROR,
        ));
        $oldKey = 'products:list:v' . (int) Cache::get('products:ver:list') . ':' . $hash;

        $cache->invalidateLists();
        Cache::put($oldKey, 'stale', 600);

        $calls = 0;
        $fresh = $cache->rememberList($filters, 1, 20, function () use (&$calls): string {
            $calls++;

            return 'fresh';
        });

        self::assertSame('fresh', $fresh);
        self::assertSame(1, $calls);
        self::assertSame('stale', Cache::get($oldKey));
    }

    public function test_remember_card_uses_the_new_version_after_invalidation(): void
    {
        $cache = $this->cache();

        $cache->rememberCard('product-1', fn (): string => 'initial');

        $versionBefore = (int) Cache::get('products:ver:card:product-1');

        $cache->invalidateCard('product-1');

        $versionAfter = (int) Cache::get('products:ver:card:product-1');

        self::assertSame($versionBefore + 1, $versionAfter);
        self::assertSame('fresh', $cache->rememberCard('product-1', fn (): string => 'fresh'));
        self::assertSame('fresh', Cache::get('products:card:product-1:v' . $versionAfter));
    }

    private function cache(
        ?ProductRepositoryInterface $productRepository = null,
        int $listTtl = 300,
        int $cardTtl = 600,
    ): ProductCache {
        return new ProductCache(
            $listTtl,
            $cardTtl,
            $productRepository ?? Mockery::mock(ProductRepositoryInterface::class),
        );
    }
}
