<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;
use App\Models\Category;

final readonly class CategoryUpdated implements EventInterface
{
    public function __construct(
        public string $categoryId,
        public string $storeId,
        public string $name,
        public string $slug,
        public ?string $parentId,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::CATEGORY_UPDATED;
    }

    /**
     * @return array{category_id: string, store_id: string, name: string, slug: string, parent_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'store_id' => $this->storeId,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
        ];
    }

    public static function fromModel(Category $category): self
    {
        return new self(
            categoryId: $category->id,
            storeId: $category->store_id,
            name: $category->name,
            slug: $category->slug,
            parentId: $category->parent_id,
        );
    }
}
