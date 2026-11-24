<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Contracts\EventInterface;
use App\Domain\Order\ActorRole;
use App\Domain\Order\OrderStatus;

abstract class OrderEvent implements EventInterface
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $storeId,
        private readonly array $items,
        private readonly OrderStatus $toStatus,
        private readonly ActorRole $actorRole,
        private readonly ?string $actorId = null,
        private readonly ?OrderStatus $fromStatus = null,
    ) {
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function storeId(): string
    {
        return $this->storeId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function fromStatus(): ?OrderStatus
    {
        return $this->fromStatus;
    }

    public function toStatus(): OrderStatus
    {
        return $this->toStatus;
    }

    public function actorRole(): ActorRole
    {
        return $this->actorRole;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'store_id' => $this->storeId,
            'items' => $this->items,
        ];
    }
}
