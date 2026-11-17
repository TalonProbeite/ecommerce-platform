<?php

declare(strict_types=1);

namespace App\Application\Payments;

use Carbon\CarbonImmutable;

final readonly class RefundRecord
{
    public function __construct(
        public string $id,
        public string $orderId,
        public ?string $orderItemId,
        public string $paymentId,
        public ?string $providerRefundId,
        public int $amount,
        public string $reason,
        public string $status,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {
    }
}
