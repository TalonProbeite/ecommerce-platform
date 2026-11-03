<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;

final class ProductImageRepository implements ProductImageRepositoryInterface
{
    public function create(array $data): ProductImage
    {
        return ProductImage::query()->create($data);
    }

    public function findByIdForProduct(
        string $imageId,
        string $productId,
    ): ?ProductImage {
        return ProductImage::query()
            ->where('product_id', $productId)
            ->whereKey($imageId)
            ->first();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function listForProduct(string $productId): Collection
    {
        return ProductImage::query()
            ->where('product_id', $productId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function update(
        ProductImage $image,
        array $data,
    ): ProductImage {
        $image->update($data);

        return $image->refresh();
    }

    public function delete(ProductImage $image): void
    {
        $image->delete();
    }

    public function firstForProduct(
        string $productId,
    ): ?ProductImage {
        return ProductImage::query()
            ->where('product_id', $productId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
