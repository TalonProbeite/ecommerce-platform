<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;


use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;


interface ProductImageRepositoryInterface
{

    /**
     * Undocumented function
     *
     * @param string $productID
     * @return Collection<int,ProductImage>
     */
    public function findByProduct(string $productID): Collection;
    public function findById(string $imageID, string $productID): ?ProductImage;
    public function save(ProductImage $productImage): ProductImage;
    public function delete(ProductImage $productImage): void;
}
