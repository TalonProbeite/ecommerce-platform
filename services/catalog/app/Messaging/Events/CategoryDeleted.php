<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;
use App\Models\Category;

final readonly class CategoryDeleted implements EventInterface
{
    public function __construct(
        public string $categoryId,
        public string $storeId,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::CATEGORY_DELETED;
    }

    /**
     * @return array{category_id: string, store_id: string}
     */
    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'store_id' => $this->storeId,
        ];
    }

    public static function fromModel(Category $category): self
    {
        return new self(
            categoryId: $category->id,
            storeId: $category->store_id,
        );
    }
}
