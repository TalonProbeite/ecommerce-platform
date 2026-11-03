<?php

namespace App\Contracts\Repositories;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;

interface ProductImageRepositoryInterface
{
    /**
     * @param array{
     *     id: string,
     *     product_id: string,
     *     object_key: string,
     *     mime_type: string|null,
     *     size: int|null
     * } $data
     */
    public function create(array $data): ProductImage;

    public function findByIdForProduct(
        string $imageId,
        string $productId,
    ): ?ProductImage;

    /** @return Collection<int, ProductImage> */
    public function listForProduct(string $productId): Collection;

    /**
     * @param array{
     *     object_key?: string,
     *     mime_type?: string|null,
     *     size?: int|null
     * } $data
     */
    public function update(
        ProductImage $image,
        array $data,
    ): ProductImage;

    public function delete(ProductImage $image): void;

    public function firstForProduct(
        string $productId,
    ): ?ProductImage;
}
