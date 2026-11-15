<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Enums\Role;
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
        Role $actorRole,
        ?string $actorId = null,
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
}
