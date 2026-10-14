<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Product;
use App\DTO\ProductFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findByID(string $id): ?Product;
    public function findByFilters(ProductFilters $filters): LengthAwarePaginator;
    public function save(Product $product): Product;
    public function delete(Product $product): void;
}
