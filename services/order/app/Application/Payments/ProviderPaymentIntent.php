<?php

declare(strict_types=1);

namespace App\Application\Payments;

final readonly class ProviderPaymentIntent
{
    public function __construct(
        public string $providerId,
        public string $status,
        public ?string $clientSecret,
    ) {
    }
}
