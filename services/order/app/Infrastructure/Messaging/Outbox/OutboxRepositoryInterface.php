<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

use Carbon\CarbonImmutable;

interface OutboxRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function insert(
        string $eventId,
        string $routingKey,
        array $payload,
        CarbonImmutable $occurredAt,
        ?string $aggregateKey = null,
    ): void;

    /**
     * Lock and fetch up to $limit unpublished outbox rows ordered by id ascending.
     * Must be called inside a database transaction.
     *
     * @return list<OutboxRow>
     */
    public function lockPendingBatch(int $limit): array;

    public function markPublished(int $id, CarbonImmutable $at): void;

    /**
     * Increment the attempt counter. When the limit is reached the row is moved
     * to outbox_failed and removed from outbox in the current transaction.
     *
     * @return bool True when the row was quarantined in outbox_failed.
     */
    public function recordFailure(OutboxRow $row, string $error, int $maxAttempts): bool;
}
