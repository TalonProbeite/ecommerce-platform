<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Non-null values imply presence. To explicitly supply null, set the matching
 * Provided argument to true. A false Provided property means leave unchanged.
 * Only nullable schema fields (parentId) may be cleared; the service must
 * reject explicit null for all other fields as an invalid business operation.
 */
final readonly class UpdateCategoryData
{
    public bool $nameProvided;
    public bool $slugProvided;
    public bool $parentIdProvided;

    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $parentId = null,
        bool $nameProvided = false,
        bool $slugProvided = false,
        bool $parentIdProvided = false,
    ) {
        $this->nameProvided = $nameProvided || $name !== null;
        $this->slugProvided = $slugProvided || $slug !== null;
        $this->parentIdProvided = $parentIdProvided || $parentId !== null;
    }
}
