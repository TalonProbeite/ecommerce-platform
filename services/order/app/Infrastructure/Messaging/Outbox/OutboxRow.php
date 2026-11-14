<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

use Carbon\CarbonImmutable;

final readonly class OutboxRow
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $eventId,
        public string $routingKey,
        public array $payload,
        public CarbonImmutable $occurredAt,
        public ?CarbonImmutable $publishedAt = null,
        public int $attempts = 0,
        public ?string $lastError = null,
        public ?string $aggregateKey = null,
    ) {
    }
}
