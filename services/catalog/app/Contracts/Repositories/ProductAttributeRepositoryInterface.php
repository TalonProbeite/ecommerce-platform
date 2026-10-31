<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use App\Models\ProductAttribute;
use App\DTO\UpdateProductAttribute;
use App\DTO\ProductAttributeData;

interface ProductAttributeRepositoryInterface
{
    /**
     * @param string $productId
     * @return Collection<int, ProductAttribute>
     */
    public function findAllByProductId(string $productId): Collection;

    public function findByNameAndProductId(string $productId, string $name): ?ProductAttribute;

    /**
     * @param array<int, ProductAttributeData> $data
     * @param string $productId
     * @return void
     */
    public function createProductAttribute(array $data, string $productId): void;

    public function updateProductAttribute(string $attributeId, UpdateProductAttribute $data): ProductAttribute;

    public function deleteProductAttribute(string $attributeId): void;
}
