<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

use App\Domain\Contracts\EventInterface;
use Carbon\CarbonImmutable;

interface OutboxWriterInterface
{
    /**
     * Enqueue an outbound event into the outbox table.
     *
     * @return string Generated event_id (UUIDv7)
     */
    public function enqueue(EventInterface $event, CarbonImmutable $occurredAt): string;
}
