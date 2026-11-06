<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class CategoryCache
{
    public function __construct(
        #[Config('catalog.cache.categories.tree_ttl')] private readonly int $treeTtl,
        #[Config('catalog.cache.version_ttl')] private readonly int $versionTtl,
    ) {
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function rememberTree(string $storeId, Closure $callback): mixed
    {
        $token = $this->readVersion($this->versionKey($storeId));

        return Cache::flexible(
            sprintf('categories:tree:%s:%s', $storeId, $token),
            [$this->treeTtl, $this->treeTtl * 2],
            $callback,
        );
    }

    public function invalidateStore(string $storeId): void
    {
        $this->bump($this->versionKey($storeId));
    }

    public function readVersion(string $key): string
    {
        $version = Cache::get($key);

        if ($version === null) {
            Cache::add($key, $this->newToken(), $this->versionTtl);
            $version = Cache::get($key);
        }

        return (string) $version;
    }

    private function bump(string $key): void
    {
        Cache::put($key, $this->newToken(), $this->versionTtl);
    }

    private function newToken(): string
    {
        return Str::random(16);
    }

    private function versionKey(string $storeId): string
    {
        return 'categories:ver:store:' . $storeId;
    }
}
