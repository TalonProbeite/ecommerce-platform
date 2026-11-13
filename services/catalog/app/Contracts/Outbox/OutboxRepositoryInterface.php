<?php

declare(strict_types=1);

namespace App\Contracts\Outbox;

use App\Outbox\OutboxRow;
use Carbon\CarbonImmutable;

interface OutboxRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function insert(string $eventId, string $routingKey, array $payload, CarbonImmutable $occurredAt): void;

    /**
     * Lock and fetch up to $limit unpublished outbox rows ordered by id ascending.
     * Must be called inside a database transaction.
     *
     * @return list<OutboxRow>
     */
    public function lockPendingBatch(int $limit): array;

    public function markPublished(int $id, CarbonImmutable $at): void;

    public function recordFailure(int $id, string $error): void;
}
