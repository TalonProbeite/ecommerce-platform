<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Override;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\ProductFilters;

class ProductRepository implements ProductRepositoryInterface
{

    public function findByID(string $id): ?Product
    {
        return Product::find($id);
    }


    public function save(Product $Product): Product
    {
        $Product->saveOrFail;
        return $Product;
    }

    public function delete(Product $Product): void
    {
        $Product->deleteOrFail();
    }


    public function findByFilters(ProductFilters $filters): LengthAwarePaginator
    {
        return Product::query()
            ->when(
                $filters->storeId !== null,
                fn($q) => $q->where('store_id', $filters->storeId)
            )
            ->when(
                $filters->categoryId !== null,
                fn($q) => $q->where('category_id', $filters->categoryId)
            )
            ->when(
                $filters->minPrice !== null,
                fn($q) => $q->where('price_per_hour', '>=', $filters->minPrice)
            )
            ->when(
                $filters->maxPrice !== null,
                fn($q) => $q->where('price_per_hour', '<=', $filters->maxPrice)
            )
            ->when(
                $filters->maxDeposit !== null,
                fn($q) => $q->where('deposit_amount', '<=', $filters->maxDeposit)
            )
            ->when(
                $filters->minRating !== null,
                fn($q) => $q->where('rating', '>=', $filters->minRating)
            )
            ->when(
                $filters->isActive !== null,
                fn($q) => $q->where('is_active', $filters->isActive)
            )
            ->orderBy($filters->sortBy->value, $filters->sortDirection->value)
            ->orderBy('id')
            ->paginate(
                perPage: $filters->limit,
                page: $filters->page,
            );
    }
}
