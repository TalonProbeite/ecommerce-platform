<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductImage;
use App\Contracts\Repositories\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductImageRepository implements ProductImageRepositoryInterface
{
    /**
     * Find all images for a given product.
     *
     * @return Collection<int, ProductImage>
     */
    public function findByProduct(string $productID): Collection
    {
        return ProductImage::where('product_id', $productID)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Find a product image by its ID and product ID.
     */
    public function findById(string $imageID, string $productID): ?ProductImage
    {
        return ProductImage::where('id', $imageID)
            ->where('product_id', $productID)
            ->first();
    }

    /**
     * Save a product image.
     */
    public function save(ProductImage $productImage): ProductImage
    {
        $productImage->saveOrFail();
        return $productImage;
    }

    /**
     * Delete a product image.
     */
    public function delete(ProductImage $productImage): void
    {
        $productImage->deleteOrFail();
    }
}
