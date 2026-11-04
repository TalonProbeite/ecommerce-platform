<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    /**
     * Find a product by its ID, optionally scoped to a store.
     */
    public function findById(string $id, ?string $storeId = null): ?Product
    {
        return Product::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->find($id);
    }

    /**
     * Lock a product row for the rest of the current transaction.
     * Must be called inside a DB transaction.
     */
    public function lockById(string $productId, string $storeId): ?Product
    {
        return Product::query()
            ->whereKey($productId)
            ->where('store_id', $storeId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Find a single product with its category, stock, attributes and images.
     */
    public function findDetailedById(string $id, ?string $storeId = null): ?Product
    {
        return Product::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->with([
                'category:id,name',
                'stock',
                'attributes' => fn ($q) => $q->select(['id', 'product_id', 'name', 'value'])
                    ->orderBy('name'),
                'images' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
            ])
            ->find($id);
    }

    /**
     * Find paginated products matching the given filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator
    {
        $availableExpr = 'COALESCE(stocks.quantity, 0) - COALESCE(stocks.reserved, 0)';

        return Product::query()
            ->select([
                'products.*',
                'categories.name as category_name',
            ])
            ->selectRaw("({$availableExpr}) as available")
            ->join('categories', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                    ->on('categories.store_id', '=', 'products.store_id');
            })
            ->leftJoin('stocks', 'stocks.product_id', '=', 'products.id')
            ->when(
                $filters->storeId !== null,
                fn ($q) => $q->where('products.store_id', $filters->storeId)
            )
            ->when(
                $filters->categoryId !== null,
                fn ($q) => $q->where('products.category_id', $filters->categoryId)
            )
            ->when(
                $filters->minPrice !== null,
                fn ($q) => $q->where('products.price_per_hour', '>=', $filters->minPrice)
            )
            ->when(
                $filters->maxPrice !== null,
                fn ($q) => $q->where('products.price_per_hour', '<=', $filters->maxPrice)
            )
            ->when(
                $filters->maxDeposit !== null,
                fn ($q) => $q->where('products.deposit_amount', '<=', $filters->maxDeposit)
            )
            ->when(
                $filters->isActive !== null,
                fn ($q) => $q->where('products.is_active', $filters->isActive)
            )
            ->when(
                $filters->isAvailable !== null,
                fn ($q) => $filters->isAvailable
                    ? $q->whereRaw("({$availableExpr}) > 0")
                    : $q->whereRaw("({$availableExpr}) <= 0")
            )
            ->orderBy("products.{$filters->sortBy->value}", $filters->sortDirection->value)
            ->orderBy('products.id')
            ->paginate(
                perPage: $filters->limit,
                page: $filters->page,
            );
    }

    /**
     * Find IDs of all products in the category, including soft-deleted ones.
     *
     * @return array<int, string>
     */
    public function findIdsByCategory(string $categoryId): array
    {
        return Product::withTrashed()
            ->where('category_id', $categoryId)
            ->pluck('id')
            ->all();
    }

    public function createProduct(string $storeId, CreateProductData $data): Product
    {
        $product = new Product();
        $product->store_id = $storeId;
        $product->fill($data->toArray());
        $product->saveOrFail();

        return $product;
    }

    public function updateProduct(string $productId, string $storeId, UpdateProductData $data): Product
    {
        $product = Product::query()
            ->where('store_id', $storeId)
            ->findOrFail($productId);
        $product->fill($data->toArray());
        $product->saveOrFail();

        return $product;
    }

    public function deleteProduct(string $productId, string $storeId): void
    {
        $product = Product::query()
            ->where('store_id', $storeId)
            ->findOrFail($productId);
        $product->deleteOrFail();
    }

    public function restoreProduct(string $productId, string $storeId): Product
    {
        $product = Product::withTrashed()
            ->where('store_id', $storeId)
            ->findOrFail($productId);
        $product->restore();

        return $product;
    }

    /**
     * Determine whether any product, including a soft-deleted one, belongs to the category.
     */
    public function hasByCategory(string $categoryId): bool
    {
        return Product::withTrashed()
            ->where('category_id', $categoryId)
            ->exists();
    }
}
