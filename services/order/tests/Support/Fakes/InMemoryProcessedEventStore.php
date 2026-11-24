<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\ProcessedEventStore;
use Carbon\CarbonImmutable;

final class InMemoryProcessedEventStore implements ProcessedEventStore
{
    /**
     * @var array<string, array{routing_key: string, processed_at: CarbonImmutable}>
     */
    private array $processed = [];

    public function markProcessed(string $eventId, string $routingKey, CarbonImmutable $at): bool
    {
        if (isset($this->processed[$eventId])) {
            return false;
        }

        $this->processed[$eventId] = [
            'routing_key' => $routingKey,
            'processed_at' => $at,
        ];

        return true;
    }

    public function wasProcessed(string $eventId): bool
    {
        return isset($this->processed[$eventId]);
    }

    /**
     * @return array<string, array{routing_key: string, processed_at: CarbonImmutable}>
     */
    public function all(): array
    {
        return $this->processed;
    }
}
