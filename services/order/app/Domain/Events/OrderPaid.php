<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Money\Money;
use App\Domain\Order\ActorRole;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;

final class OrderPaid extends OrderEvent
{
    public function __construct(
        string $orderId,
        string $storeId,
        private readonly string $customerId,
        private readonly int $amountTotal,
        private readonly CarbonImmutable $paidAt,
        ?string $actorId = null,
    ) {
        parent::__construct(
            orderId: $orderId,
            storeId: $storeId,
            items: [],
            toStatus: OrderStatus::PAID,
            actorRole: ActorRole::STRIPE,
            actorId: $actorId,
            fromStatus: OrderStatus::PAYMENT_PENDING,
        );
    }

    public function routingKey(): string
    {
        return 'order.paid';
    }

    /**
     * @return array{order_id: string, store_id: string, customer_id: string, amount_total: int, currency: string, paid_at: string}
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId(),
            'store_id' => $this->storeId(),
            'customer_id' => $this->customerId,
            'amount_total' => $this->amountTotal,
            'currency' => Money::CURRENCY,
            'paid_at' => $this->paidAt->utc()->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
