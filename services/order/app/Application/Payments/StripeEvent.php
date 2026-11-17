<?php

declare(strict_types=1);

namespace App\Application\Payments;

final readonly class StripeEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public array $data,
    ) {
    }
}
