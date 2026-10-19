<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductImage;
use App\Contracts\Repositories\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductImageRepository implements ProductImageRepositoryInterface
{
    public function findByProduct(string $productID): Collection
    {
        return ProductImage::where('product_id', $productID)
            ->get();
    }

    public function findById(string $imageID, string $productID): ?ProductImage
    {
        return ProductImage::where('id', $imageID)
            ->where('product_id', $productID)
            ->get();
    }

    public function save(ProductImage $productImage): ProductImage
    {
        $productImage->saveOrFail;
        return $productImage;
    }

    public function delete(ProductImage $productImage): void
    {
        $productImage->deleteOrFail();
    }
}
