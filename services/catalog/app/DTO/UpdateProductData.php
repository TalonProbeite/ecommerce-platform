<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Non-null values imply presence. To explicitly supply null, set the matching
 * Provided argument to true. A false Provided property means leave unchanged.
 * Only nullable schema fields (description) may be cleared; the service must
 * reject explicit null for all other fields as an invalid business operation.
 * Monetary amounts, where present, are integer kopecks; rent is priced per hour.
 */
final readonly class UpdateProductData
{
    public bool $nameProvided;
    public bool $slugProvided;
    public bool $categoryIdProvided;
    public bool $pricePerHourProvided;
    public bool $descriptionProvided;
    public bool $depositAmountProvided;
    public bool $isActiveProvided;

    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $categoryId = null,
        public ?int $pricePerHour = null,
        public ?string $description = null,
        public ?int $depositAmount = null,
        public ?bool $isActive = null,
        bool $nameProvided = false,
        bool $slugProvided = false,
        bool $categoryIdProvided = false,
        bool $pricePerHourProvided = false,
        bool $descriptionProvided = false,
        bool $depositAmountProvided = false,
        bool $isActiveProvided = false,
        /**
         * @var array<int, ProductAttributeData>|null
         */
        public ?array $attributes = null,
    ) {
        $this->nameProvided = $nameProvided || $name !== null;
        $this->slugProvided = $slugProvided || $slug !== null;
        $this->categoryIdProvided = $categoryIdProvided || $categoryId !== null;
        $this->pricePerHourProvided = $pricePerHourProvided || $pricePerHour !== null;
        $this->descriptionProvided = $descriptionProvided || $description !== null;
        $this->depositAmountProvided = $depositAmountProvided || $depositAmount !== null;
        $this->isActiveProvided = $isActiveProvided || $isActive !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            categoryId: isset($data['category_id']) ? (string) $data['category_id'] : null,
            pricePerHour: isset($data['price_per_hour']) ? (int) $data['price_per_hour'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            depositAmount: isset($data['deposit_amount']) ? (int) $data['deposit_amount'] : null,
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : null,
            nameProvided: array_key_exists('name', $data),
            slugProvided: array_key_exists('slug', $data),
            categoryIdProvided: array_key_exists('category_id', $data),
            pricePerHourProvided: array_key_exists('price_per_hour', $data),
            descriptionProvided: array_key_exists('description', $data),
            depositAmountProvided: array_key_exists('deposit_amount', $data),
            isActiveProvided: array_key_exists('is_active', $data),
            attributes: array_key_exists('attributes', $data)
                ? self::mapAttributes(is_array($data['attributes']) ? $data['attributes'] : [])
                : null,
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
     * @return array<string, string|int|bool|null>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->nameProvided) {
            $data['name'] = $this->name;
        }
        if ($this->slugProvided) {
            $data['slug'] = $this->slug;
        }

        if ($this->categoryIdProvided) {
            $data["category_id"] = $this->categoryId;
        }

        if ($this->pricePerHourProvided) {
            $data['price_per_hour'] = $this->pricePerHour;
        }

        if ($this->descriptionProvided) {
            $data['description'] = $this->description;
        }

        if ($this->depositAmountProvided) {
            $data['deposit_amount'] = $this->depositAmount;
        }

        if ($this->isActiveProvided) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
