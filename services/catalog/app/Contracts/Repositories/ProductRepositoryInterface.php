<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\ProductFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * Find a product by its ID, optionally scoped to a store.
     */
    public function findById(string $id, ?string $storeId = null): ?Product;

    /**
     * Find a product with related data by its ID, optionally scoped to a store.
     */
    public function findDetailedById(string $id, ?string $storeId = null): ?Product;

    /**
     * Check if a product with the given slug exists in a store, optionally excluding a product ID.
     */
    public function existsBySlug(string $slug, string $storeId, ?string $excludeProductId = null): bool;

    /**
     * Find paginated products matching the given filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;

    /**
     * Save a product.
     */
    public function save(Product $product): Product;

    /**
     * Delete a product.
     */
    public function delete(Product $product): void;
}
