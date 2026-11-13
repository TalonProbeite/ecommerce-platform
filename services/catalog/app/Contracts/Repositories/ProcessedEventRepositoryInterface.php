<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Carbon\CarbonImmutable;

interface ProcessedEventRepositoryInterface
{
    /**
     * Mark an inbound event as processed.
     *
     * IMPORTANT: this method must be called inside the same transaction as the
     * business change. The unique event_id insert is the idempotency guard; if
     * the business change fails, the transaction rollback also removes the mark.
     *
     * @return bool true when the event was marked now; false when it was already processed.
     */
    public function markProcessed(string $eventId, string $routingKey, CarbonImmutable $at): bool;
}
