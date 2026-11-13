<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxRepositoryInterface;
use App\Contracts\Outbox\OutboxWriterInterface;
use App\Domain\Contracts\EventInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class OutboxWriter implements OutboxWriterInterface
{
    public function __construct(
        private OutboxRepositoryInterface $repository,
    ) {
    }

    public function enqueue(EventInterface $event, CarbonImmutable $occurredAt): string
    {
        $eventId = (string) Str::uuid7();

        $this->repository->insert(
            eventId: $eventId,
            routingKey: $event->routingKey(),
            payload: $event->toArray(),
            occurredAt: $occurredAt,
        );

        return $eventId;
    }
}
