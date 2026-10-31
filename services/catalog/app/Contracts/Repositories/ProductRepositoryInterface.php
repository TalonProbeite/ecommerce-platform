<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\ProductFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\UpdateProductData;
use App\DTO\CreateProductData;

interface ProductRepositoryInterface
{
    /**
     * Find a product by its ID, optionally scoped to a store.
     */
    public function findById(string $id, ?string $storeId = null): ?Product;

    /**
     * Find a product with related data by its ID, optionally scoped to a store.
     */
    public function findDetailedById(string $id): ?Product;


    /**
     * Find paginated products matching the given filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;

    public function updateProduct(string $productId, UpdateProductData $data): Product;

    public function restoreProduct(string $productId): Product;

    public function deleteProduct(string $productId): void;

    public function createProduct(string $productId, CreateProductData $data): Product;
}
