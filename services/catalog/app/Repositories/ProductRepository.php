<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTO\ProductFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function findById(string $id, ?string $storeId = null): ?Product
    {
        return Product::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->find($id);
    }

    public function findDetailedById(string $id, ?string $storeId = null): ?Product
    {
        return Product::with([
            'category',
            'attributes',
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'stock',
        ])
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->find($id);
    }

    public function existsBySlug(string $slug, string $storeId, ?string $excludeProductId = null): bool
    {
        return Product::withTrashed()
            ->where('slug', $slug)
            ->where('store_id', $storeId)
            ->when($excludeProductId !== null, fn ($q) => $q->where('id', '!=', $excludeProductId))
            ->exists();
    }

    public function save(Product $product): Product
    {
        $product->saveOrFail();
        return $product;
    }

    public function delete(Product $product): void
    {
        $product->deleteOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator
    {
        return Product::query()
            ->with(['images' => fn ($q) => $q->where('is_main', true)])
            ->when(
                $filters->storeId !== null,
                fn ($q) => $q->where('store_id', $filters->storeId)
            )
            ->when(
                $filters->categoryId !== null,
                fn ($q) => $q->where('category_id', $filters->categoryId)
            )
            ->when(
                $filters->minPrice !== null,
                fn ($q) => $q->where('price_per_hour', '>=', $filters->minPrice)
            )
            ->when(
                $filters->maxPrice !== null,
                fn ($q) => $q->where('price_per_hour', '<=', $filters->maxPrice)
            )
            ->when(
                $filters->maxDeposit !== null,
                fn ($q) => $q->where('deposit_amount', '<=', $filters->maxDeposit)
            )
            ->when(
                $filters->minRating !== null,
                fn ($q) => $q->where('rating', '>=', $filters->minRating)
            )
            ->when(
                $filters->isActive !== null,
                fn ($q) => $q->where('is_active', $filters->isActive)
            )
            ->orderBy($filters->sortBy->value, $filters->sortDirection->value)
            ->orderBy('id')
            ->paginate(
                perPage: $filters->limit,
                page: $filters->page,
            );
    }
}
