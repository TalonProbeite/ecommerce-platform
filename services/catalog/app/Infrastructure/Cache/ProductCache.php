<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Repositories\ProductRepositoryInterface;
use BackedEnum;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JsonSerializable;
use UnitEnum;

final class ProductCache
{
    private const LIST_VERSION_KEY = 'products:ver:list';

    public function __construct(
        #[Config('catalog.cache.products.list_ttl')] private readonly int $listTtl,
        #[Config('catalog.cache.products.card_ttl')] private readonly int $cardTtl,
        #[Config('catalog.cache.version_ttl')] private readonly int $versionTtl,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param Closure(): mixed $callback
     */
    public function rememberList(array $filters, int $page, int $perPage, Closure $callback): mixed
    {
        $token = $this->readVersion(self::LIST_VERSION_KEY);

        $hash = sha1((string) json_encode(
            $this->normalize(['filters' => $filters, 'page' => $page, 'per_page' => $perPage]),
            JSON_THROW_ON_ERROR,
        ));

        return Cache::flexible(
            sprintf('products:list:%s:%s', $token, $hash),
            [$this->listTtl, $this->listTtl * 2],
            $callback,
        );
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function rememberCard(string $scope, string $productId, Closure $callback): mixed
    {
        $token = $this->readVersion($this->cardVersionKey($productId));

        return Cache::flexible(
            sprintf('products:card:%s:%s:%s', $scope, $productId, $token),
            [$this->cardTtl, $this->cardTtl * 2],
            $callback,
        );
    }

    public function invalidateCard(string $productId): void
    {
        $this->bump($this->cardVersionKey($productId));
    }

    public function invalidateLists(): void
    {
        $this->bump(self::LIST_VERSION_KEY);
    }

    public function invalidateCategory(string $categoryId): void
    {
        foreach ($this->productRepository->findIdsByCategory($categoryId) as $productId) {
            $this->invalidateCard($productId);
        }

        $this->invalidateLists();
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

    private function cardVersionKey(string $productId): string
    {
        return 'products:ver:card:' . $productId;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if (is_array($value)) {
            ksort($value);

            foreach ($value as $key => $item) {
                $value[$key] = $this->normalize($item);
            }

            return $value;
        }

        if (is_object($value)) {
            return $this->normalize(get_object_vars($value));
        }

        return $value;
    }
}
