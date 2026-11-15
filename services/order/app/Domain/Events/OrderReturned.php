<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Enums\Role;
use App\Domain\Order\OrderStatus;

final class OrderReturned extends OrderEvent
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        string $orderId,
        string $storeId,
        array $items,
        OrderStatus $fromStatus,
        OrderStatus $toStatus,
        Role $actorRole = Role::STORE_MANAGER,
        ?string $actorId = null,
    ) {
        parent::__construct(
            orderId: $orderId,
            storeId: $storeId,
            items: $items,
            toStatus: $toStatus,
            actorRole: $actorRole,
            actorId: $actorId,
            fromStatus: $fromStatus,
        );
    }

    public function routingKey(): string
    {
        return 'order.returned';
    }
}
