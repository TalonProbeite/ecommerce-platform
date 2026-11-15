<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Order\ActorRole;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;

final readonly class OrderStatusChanged
{
    /**
     * @param list<string> $itemIds
     */
    public function __construct(
        public string $orderId,
        public ?OrderStatus $fromStatus,
        public OrderStatus $toStatus,
        public ?string $actorId,
        public ActorRole $actorRole,
        public CarbonImmutable $at,
        public array $itemIds = [],
    ) {
    }
}
