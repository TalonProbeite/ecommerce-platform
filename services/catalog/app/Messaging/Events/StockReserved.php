<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;

final readonly class StockReserved implements EventInterface
{
    public function __construct(
        public string $orderId,
        public string $storeId,
        public string $productId,
        public int $quantity,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::STOCK_RESERVED;
    }

    /**
     * @return array{order_id: string, store_id: string, product_id: string, quantity: int}
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'store_id' => $this->storeId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
        ];
    }
}
