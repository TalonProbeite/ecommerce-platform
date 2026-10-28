<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Catalog input only; store ownership is supplied separately where applicable.
 */
final readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $parentId = null,
    ) {
    }

    /**
     * @return array{name: string, slug: string|null, parent_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
        ];
    }
}
