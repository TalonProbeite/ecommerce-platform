<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Mutations reject missing entities, invalid input, or invalid store ownership
 * as business-operation errors. Update DTO fields marked absent stay unchanged;
 * explicit null is accepted only for nullable schema fields.
 */
interface ProductServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function list(ProductFilters $filters): LengthAwarePaginator;

    /**
     * Load category, attributes, images and stock. Scoped to the store when supplied.
     */
    public function get(
        string $productId,
        ?string $storeId = null,
    ): Product;

    /**
     * Create the product, its initial stock and attributes in one transaction.
     */
    public function create(
        string $storeId,
        CreateProductData $data,
    ): Product;

    /**
     * Replace attributes only when the attributes field is present; an empty
     * array clears all attributes.
     */
    public function update(
        string $storeId,
        string $productId,
        UpdateProductData $data,
    ): Product;

    /**
     * Reject deletion while active reserved or issued reservations exist.
     */
    public function delete(
        string $storeId,
        string $productId,
    ): void;

    public function restore(
        string $storeId,
        string $productId,
    ): Product;
}
