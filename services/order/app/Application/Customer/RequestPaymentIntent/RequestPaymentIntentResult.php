<?php

declare(strict_types=1);

namespace App\Application\Customer\RequestPaymentIntent;

final readonly class RequestPaymentIntentResult
{
    public function __construct(
        public string $clientSecret,
        public string $status,
        public int $amountTotal,
        public string $currency,
        public bool $created,
    ) {
    }
}
