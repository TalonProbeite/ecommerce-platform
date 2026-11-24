<?php

declare(strict_types=1);

namespace App\Application\Payments;

final readonly class PaymentIntentRequest
{
    public function __construct(
        public string $orderId,
        public int $amount,
        public string $currency,
    ) {
    }
}
