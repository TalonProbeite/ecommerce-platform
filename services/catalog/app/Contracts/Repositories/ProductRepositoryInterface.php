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
     * Find a product visible on the public storefront by its ID.
     */
    public function findPublicById(string $productId): ?Product;

    /**
     * Lock a product row for the rest of the current transaction.
     * Must be called inside a DB transaction.
     */
    public function lockById(string $productId, string $storeId): ?Product;

    /**
     * Find a product with related data by its ID, optionally scoped to a store and public visibility.
     */
    public function findDetailedById(string $id, ?string $storeId = null, bool $publicOnly = false): ?Product;

    /**
     * Find paginated products matching the given filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;

    /**
     * Find IDs of all products in the category, including soft-deleted ones.
     *
     * @return array<int, string>
     */
    public function findIdsByCategory(string $categoryId): array;

    /**
     * Find IDs of all products in the store, including soft-deleted ones.
     *
     * @return array<int, string>
     */
    public function findIdsByStore(string $storeId): array;

    /**
     * Find a soft-deleted product by its ID and store.
     */
    public function findTrashedByIdAndStore(string $productId, string $storeId): ?Product;

    public function createProduct(string $storeId, CreateProductData $data): Product;

    public function updateProduct(string $productId, string $storeId, UpdateProductData $data): ?Product;

    public function deleteProduct(string $productId, string $storeId): void;

    public function restoreProduct(string $productId, string $storeId): ?Product;

    /**
     * Determine whether any product, including a soft-deleted one, belongs to the category.
     */
    public function hasByCategory(string $categoryId): bool;
}
