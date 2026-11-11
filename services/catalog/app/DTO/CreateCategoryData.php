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
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            parentId: isset($data['parent_id']) ? (string) $data['parent_id'] : null,
        );
    }

    /**
     * @return array{name: string, slug: string, parent_id: string|null}
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
