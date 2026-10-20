<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTO\CreateProductData;
use App\DTO\UpdateProductData;
use App\Models\Product;
use App\DTO\ProductFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Mutations reject missing entities, invalid input, or invalid store ownership
 * as business-operation errors. Update DTO fields marked absent stay unchanged;
 * explicit null is accepted only for nullable schema fields.
 * Owns catalog business operations; reservation and physical equipment movements
 * belong to StockServiceInterface.
 */
interface ProductServiceInterface
{
    /** @return LengthAwarePaginator<int, Product> */
    public function list(ProductFilters $filters): LengthAwarePaginator;

    /** Load category, attributes, images ordered by sort_order, and stock. Returns null if not found. */
    public function get(string $productId, string $storeId): ?Product;

    public function create(string $storeId, CreateProductData $data): Product;

    public function update(string $productId, string $storeId, UpdateProductData $data): Product;

    /**
     * Reject deletion while active reserved/issued reservations or other blocking dependencies exist.
     * Enforce business checks explicitly: foreign keys do not guard soft deletion.
     */
    public function delete(string $productId, string $storeId): void;
}
