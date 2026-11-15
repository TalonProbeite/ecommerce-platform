<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Enums\Role;
use App\Domain\Order\OrderStatus;

final class OrderCreated extends OrderEvent
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        string $orderId,
        string $storeId,
        array $items,
        ?string $customerId = null,
    ) {
        parent::__construct(
            orderId: $orderId,
            storeId: $storeId,
            items: $items,
            toStatus: OrderStatus::CREATED,
            actorRole: Role::CUSTOMER,
            actorId: $customerId,
        );
    }

    public function routingKey(): string
    {
        return 'order.created';
    }
}
