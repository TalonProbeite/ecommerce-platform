<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Domain\Contracts\EventInterface;
use App\Messaging\RoutingKeys;
use App\Models\Product;

final readonly class ProductUpdated implements EventInterface
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public string $name,
        public int $pricePerHour,
        public string $categoryId,
        public int $depositAmount,
        public bool $isActive,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::PRODUCT_UPDATED;
    }

    /**
     * @return array{product_id: string, store_id: string, category_id: string, name: string, price_per_hour: int, deposit_amount: int, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'price_per_hour' => $this->pricePerHour,
            'deposit_amount' => $this->depositAmount,
            'is_active' => $this->isActive,
        ];
    }

    public static function fromModel(Product $product): self
    {
        return new self(
            productId: $product->id,
            storeId: $product->store_id,
            name: $product->name,
            pricePerHour: $product->price_per_hour,
            categoryId: (string) $product->category_id,
            depositAmount: (int) $product->deposit_amount,
            isActive: (bool) $product->is_active,
        );
    }
}
