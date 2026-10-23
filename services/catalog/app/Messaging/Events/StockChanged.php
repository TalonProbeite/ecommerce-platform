<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\RoutingKeys;

/**
 * Quantity counts usable equipment; reserved includes both reserved and issued units.
 */
final readonly class StockChanged implements EventInterface
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public int $quantity,
        public int $reserved,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::STOCK_CHANGED;
    }

    /**
     * @return array{product_id: string, store_id: string, quantity: int, reserved: int, available: int}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'quantity' => $this->quantity,
            'reserved' => $this->reserved,
            'available' => $this->quantity - $this->reserved,
        ];
    }
}
