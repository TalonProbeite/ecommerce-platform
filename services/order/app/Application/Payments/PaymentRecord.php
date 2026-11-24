<?php

declare(strict_types=1);

namespace App\Application\Payments;

use Carbon\CarbonImmutable;

final readonly class PaymentRecord
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $provider,
        public string $providerPaymentId,
        public int $amount,
        public string $currency,
        public string $status,
        public ?string $failureCode,
        public ?CarbonImmutable $succeededAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {
    }
}
