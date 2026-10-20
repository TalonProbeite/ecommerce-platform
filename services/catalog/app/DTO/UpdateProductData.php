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
    ) {
        $this->nameProvided = $nameProvided || $name !== null;
        $this->slugProvided = $slugProvided || $slug !== null;
        $this->categoryIdProvided = $categoryIdProvided || $categoryId !== null;
        $this->pricePerHourProvided = $pricePerHourProvided || $pricePerHour !== null;
        $this->descriptionProvided = $descriptionProvided || $description !== null;
        $this->depositAmountProvided = $depositAmountProvided || $depositAmount !== null;
        $this->isActiveProvided = $isActiveProvided || $isActive !== null;
    }
}
