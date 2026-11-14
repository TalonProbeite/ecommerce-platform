<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Contracts\EventInterface;
use App\Infrastructure\Messaging\Outbox\OutboxWriterInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class FakeOutboxWriter implements OutboxWriterInterface
{
    /**
     * @var list<array{event: EventInterface, occurredAt: CarbonImmutable, eventId: string}>
     */
    public array $enqueued = [];

    public function enqueue(EventInterface $event, CarbonImmutable $occurredAt): string
    {
        $eventId = (string) Str::uuid7();

        $this->enqueued[] = [
            'event' => $event,
            'occurredAt' => $occurredAt,
            'eventId' => $eventId,
        ];

        return $eventId;
    }

    /**
     * @return list<EventInterface>
     */
    public function events(): array
    {
        return array_map(static fn (array $entry): EventInterface => $entry['event'], $this->enqueued);
    }

    /**
     * @template T of EventInterface
     * @param class-string<T> $class
     * @return list<T>
     */
    public function eventsOf(string $class): array
    {
        /** @var list<T> */
        return array_values(array_filter(
            $this->events(),
            static fn (EventInterface $event): bool => $event instanceof $class,
        ));
    }

    public function count(): int
    {
        return count($this->enqueued);
    }

    public function reset(): void
    {
        $this->enqueued = [];
    }
}
