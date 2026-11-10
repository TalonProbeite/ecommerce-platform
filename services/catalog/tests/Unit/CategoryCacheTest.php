<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Cache\CategoryCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_remember_tree_caches_the_result(): void
    {
        $cache = $this->cache();
        $calls = 0;
        $callback = function () use (&$calls): string {
            $calls++;

            return 'tree';
        };

        self::assertSame('tree', $cache->rememberTree('store-1', $callback));
        self::assertSame('tree', $cache->rememberTree('store-1', $callback));
        self::assertSame(1, $calls);
    }

    public function test_invalidate_store_only_resets_the_requested_store(): void
    {
        $cache = $this->cache();
        $firstCalls = 0;
        $secondCalls = 0;

        $cache->rememberTree('store-1', function () use (&$firstCalls): string {
            $firstCalls++;

            return 'store-1';
        });
        $cache->rememberTree('store-2', function () use (&$secondCalls): string {
            $secondCalls++;

            return 'store-2';
        });

        $cache->invalidateStore('store-1');

        self::assertSame('store-1-refreshed', $cache->rememberTree('store-1', function () use (&$firstCalls): string {
            $firstCalls++;

            return 'store-1-refreshed';
        }));
        self::assertSame('store-2', $cache->rememberTree('store-2', function () use (&$secondCalls): string {
            $secondCalls++;

            return 'store-2-refreshed';
        }));

        self::assertSame(2, $firstCalls);
        self::assertSame(1, $secondCalls);
    }

    public function test_late_writer_to_the_old_tree_key_cannot_overwrite_the_fresh_tree(): void
    {
        $cache = $this->cache();

        $cache->rememberTree('store-1', fn (): string => 'initial');

        $oldKey = 'categories:tree:store-1:' . (string) Cache::get('categories:ver:store:store-1');

        $cache->invalidateStore('store-1');
        Cache::put($oldKey, 'stale', 600);

        $calls = 0;
        $fresh = $cache->rememberTree('store-1', function () use (&$calls): string {
            $calls++;

            return 'fresh';
        });

        self::assertSame('fresh', $fresh);
        self::assertSame(1, $calls);
        self::assertSame('stale', Cache::get($oldKey));
    }

    private function cache(int $treeTtl = 1800, int $versionTtl = 86400): CategoryCache
    {
        return new CategoryCache($treeTtl, $versionTtl);
    }
}
