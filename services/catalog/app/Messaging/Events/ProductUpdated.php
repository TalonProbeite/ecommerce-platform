<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;

final readonly class ProductUpdated implements EventInterface
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public string $name,
        public int $pricePerHour,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::PRODUCT_UPDATED;
    }

    /**
     * @return array{product_id: string, store_id: string, name: string, price_per_hour: int}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'name' => $this->name,
            'price_per_hour' => $this->pricePerHour,
        ];
    }
}
