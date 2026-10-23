<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;

interface ProductImageRepositoryInterface
{
    /**
     * Find all images for a given product.
     *
     * @return Collection<int, ProductImage>
     */
    public function findByProduct(string $productID): Collection;

    /**
     * Find a product image by its ID and product ID.
     */
    public function findById(string $imageID, string $productID): ?ProductImage;

    /**
     * Save a product image.
     */
    public function save(ProductImage $productImage): ProductImage;

    /**
     * Delete a product image.
     */
    public function delete(ProductImage $productImage): void;
}
