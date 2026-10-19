<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\ProductFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findById(string $id, ?string $storeId = null): ?Product;

    public function findDetailedById(string $id, ?string $storeId = null): ?Product;

    public function existsBySlug(string $slug, string $storeId, ?string $excludeProductId = null): bool;

    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;

    public function save(Product $product): Product;

    public function delete(Product $product): void;
}
