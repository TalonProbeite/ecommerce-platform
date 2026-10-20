<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;

final readonly class ProductDeleted implements EventInterface
{
    public function __construct(
        public string $productId,
        public string $storeId,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::PRODUCT_DELETED;
    }

    /**
     * @return array{product_id: string, store_id: string}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
        ];
    }
}
