<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Order\OrderStatus;
use App\Enums\Role;
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
        public Role $actorRole,
        public CarbonImmutable $at,
        public array $itemIds = [],
    ) {
    }
}
