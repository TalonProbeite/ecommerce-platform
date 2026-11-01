<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductAttributeRepositoryInterface;
use App\DTO\ProductAttributeData;
use App\DTO\UpdateProductAttribute;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;

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
     * @param array<int, ProductAttributeData> $data
     */
    public function createProductAttribute(array $data, string $productId): void
    {
        if ($data === []) {
            return;
        }

        $now = now();
        $rows = array_map(
            static fn (ProductAttributeData $dto): array => [
                'id' => (string) Uuid::uuid7(),
                'product_id' => $productId,
                ...$dto->toArray(),
                'created_at' => $now,
                'updated_at' => $now,
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

    public function deleteAllByProductId(string $productId): void
    {
        ProductAttribute::query()
            ->where('product_id', $productId)
            ->delete();
    }
}
