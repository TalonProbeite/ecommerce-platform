<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
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
     * Find paginated products matching the given filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;

    public function createProduct(string $productId, string $storeId, CreateProductData $data): Product;

    public function updateProduct(string $productId, string $storeId, UpdateProductData $data): Product;

    public function deleteProduct(string $productId, string $storeId): void;

    public function restoreProduct(string $productId, string $storeId): Product;

    /**
     * Determine whether any product, including a soft-deleted one, belongs to the category.
     */
    public function hasByCategory(string $categoryId): bool;
}
