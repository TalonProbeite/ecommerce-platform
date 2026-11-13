<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Domain\Contracts\EventInterface;
use App\Messaging\RoutingKeys;

final readonly class StockReservationFailed implements EventInterface
{
    public function __construct(
        public string $orderId,
        public string $storeId,
        public StockReservationFailureReason $reason,
    ) {
    }

    public function routingKey(): string
    {
        return RoutingKeys::STOCK_RESERVATION_FAILED;
    }

    /**
     * @return array{order_id: string, store_id: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'store_id' => $this->storeId,
            'reason' => $this->reason->value,
        ];
    }
}
