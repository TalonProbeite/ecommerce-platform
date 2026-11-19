<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Order\ActorRole;
use App\Domain\Order\OrderStatus;

final class OrderCancelled extends OrderEvent
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        string $orderId,
        string $storeId,
        array $items,
        OrderStatus $fromStatus,
        ActorRole $actorRole,
        ?string $actorId = null,
        private readonly ?string $customerId = null,
    ) {
        parent::__construct(
            orderId: $orderId,
            storeId: $storeId,
            items: $items,
            toStatus: OrderStatus::CANCELLED,
            actorRole: $actorRole,
            actorId: $actorId,
            fromStatus: $fromStatus,
        );
    }

    public function routingKey(): string
    {
        return 'order.cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = parent::toArray();

        if ($this->customerId !== null) {
            $payload['customer_id'] = $this->customerId;
        }

        return $payload;
    }
}
