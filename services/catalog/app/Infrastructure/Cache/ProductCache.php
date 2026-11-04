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
    public const LIST_TAG = 'products:list';

    public function __construct(
        #[Config('catalog.cache.products.list_ttl')] private readonly int $listTtl,
        #[Config('catalog.cache.products.card_ttl')] private readonly int $cardTtl,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public static function cardTag(string $productId): string
    {
        return "product:{$productId}";
    }

    /**
     * @param array<string, mixed> $filters
     * @param Closure(): mixed $callback
     */
    public function rememberList(array $filters, int $page, int $perPage, Closure $callback): mixed
    {
        $key = 'products:list:' . sha1((string) json_encode(
            $this->normalize(['filters' => $filters, 'page' => $page, 'per_page' => $perPage]),
            JSON_THROW_ON_ERROR,
        ));

        return Cache::tags([self::LIST_TAG])->remember($key, $this->listTtl, $callback);
    }

    /**
     * @param Closure(): mixed $callback
     */
    public function rememberCard(string $productId, Closure $callback): mixed
    {
        return Cache::tags([self::cardTag($productId)])->remember(
            "products:card:{$productId}",
            $this->cardTtl,
            $callback,
        );
    }

    public function forgetProduct(string $productId): void
    {
        Cache::tags([self::cardTag($productId)])->flush();
    }

    public function forgetLists(): void
    {
        Cache::tags([self::LIST_TAG])->flush();
    }

    public function forgetProductAndLists(string $productId): void
    {
        $this->forgetProduct($productId);
        $this->forgetLists();
    }

    public function forgetCategoryCards(string $categoryId): void
    {
        foreach ($this->productRepository->findIdsByCategory($categoryId) as $productId) {
            $this->forgetProduct($productId);
        }

        $this->forgetLists();
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
