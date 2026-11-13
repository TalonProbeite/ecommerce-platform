<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Domain\Contracts\EventInterface;
use App\Messaging\RoutingKeys;

final readonly class StockReserved implements EventInterface
{
    public function __construct(
        public string $orderId,
        public string $storeId,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::STOCK_RESERVED;
    }

    /**
     * @return array{order_id: string, store_id: string}
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'store_id' => $this->storeId,
        ];
    }
}
