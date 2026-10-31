<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductAttribute;
use App\DTO\ProductAttributeData;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\UpdateProductAttribute;
use App\Contracts\Repositories\ProductAttributeRepositoryInterface;

class ProductAttributeRepository implements ProductAttributeRepositoryInterface
{
    public function findAllByProductId(string $productId): Collection
    {
        return ProductAttribute::where('product_id', $productId)
            ->get();
    }

    public function findByNameAndProductId(string $productId, string $name): ?ProductAttribute
    {
        return ProductAttribute::where('product_id', $productId)
            ->where('name', $name)
            ->first();
    }

    /**
     * Undocumented function
     *
     * @param array<int, ProductAttributeData> $data
     * @param string $productId
     * @return void
     */
    public function createProductAttribute(array $data, string $productId): void
    {
        if ($data === []) {
            return;
        }
        $rows = array_map(
            static fn (ProductAttributeData $dto): array => [
                ...$dto->toArray(),
                'product_id' => $productId,

            ],
            $data,
        );

        ProductAttribute::query()->insert($rows);
    }

    public function updateProductAttribute(string $attributeId, UpdateProductAttribute $data): ProductAttribute
    {
        $attribute = ProductAttribute::where('id', $attributeId)
            ->firstOrFail();
        $attribute->fill($data->toArray());
        $attribute->saveOrFail();

        return $attribute;
    }

    public function deleteProductAttribute(string $attributeId): void
    {
        $attribute = ProductAttribute::findOrFail($attributeId);
        $attribute->deleteOrFail();
    }
}
