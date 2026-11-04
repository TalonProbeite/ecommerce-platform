<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Repositories\ProductRepositoryInterface;
use BackedEnum;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use JsonSerializable;
use UnitEnum;

final class ProductCache
{
    private const LIST_VERSION_KEY = 'products:ver:list';

    public function __construct(
        #[Config('catalog.cache.products.list_ttl')] private readonly int $listTtl,
        #[Config('catalog.cache.products.card_ttl')] private readonly int $cardTtl,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param Closure(): mixed $callback
     */
    public function rememberList(array $filters, int $page, int $perPage, Closure $callback): mixed
    {
        $version = $this->currentVersion(self::LIST_VERSION_KEY);

        $hash = sha1((string) json_encode(
            $this->normalize(['filters' => $filters, 'page' => $page, 'per_page' => $perPage]),
            JSON_THROW_ON_ERROR,
        ));

        return Cache::remember(
            sprintf('products:list:v%d:%s', $version, $hash),
            $this->listTtl,
            $callback,
        );
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function rememberCard(string $productId, Closure $callback): mixed
    {
        $version = $this->currentVersion($this->cardVersionKey($productId));

        return Cache::remember(
            sprintf('products:card:%s:v%d', $productId, $version),
            $this->cardTtl,
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

    private function currentVersion(string $key): int
    {
        Cache::add($key, 1, null);

        return (int) Cache::get($key);
    }

    private function bump(string $key): void
    {
        Cache::add($key, 1, null);
        Cache::increment($key);
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
