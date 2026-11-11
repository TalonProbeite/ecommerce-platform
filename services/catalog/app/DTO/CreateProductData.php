<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Catalog input only; monetary amounts are integer kopecks, with rent priced per hour.
 */
final readonly class CreateProductData
{
    /**
     * @param array<int, ProductAttributeData> $attributes
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $categoryId,
        public int $pricePerHour,
        public ?string $description = null,
        public int $depositAmount = 0,
        public bool $isActive = true,
        public int $quantity = 0,
        public array $attributes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            categoryId: (string) $data['category_id'],
            pricePerHour: (int) $data['price_per_hour'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            depositAmount: isset($data['deposit_amount']) ? (int) $data['deposit_amount'] : 0,
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : 0,
            attributes: self::mapAttributes(
                is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            ),
        );
    }

    /**
     * @param array<int, array{name: string, value: string}> $attributes
     * @return array<int, ProductAttributeData>
     */
    private static function mapAttributes(array $attributes): array
    {
        return array_map(
            static fn (array $attribute): ProductAttributeData => new ProductAttributeData(
                name: $attribute['name'],
                value: $attribute['value'],
            ),
            $attributes,
        );
    }

    /**
     * @return array<string, int|bool|string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'category_id' => $this->categoryId,
            'price_per_hour' => $this->pricePerHour,
            'description' => $this->description,
            'deposit_amount' => $this->depositAmount,
            'is_active' => $this->isActive,
        ];
    }
}
