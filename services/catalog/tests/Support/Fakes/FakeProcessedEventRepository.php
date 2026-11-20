<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Contracts\Repositories\ProcessedEventRepositoryInterface;
use Carbon\CarbonImmutable;

final class FakeProcessedEventRepository implements ProcessedEventRepositoryInterface
{
    /**
     * @var array<string, string> event_id => routing_key
     */
    private array $processed = [];

    public function markProcessed(string $eventId, string $routingKey, CarbonImmutable $at): bool
    {
        if (isset($this->processed[$eventId])) {
            return false;
        }

        $this->processed[$eventId] = $routingKey;

        return true;
    }

    public function wasProcessed(string $eventId): bool
    {
        return isset($this->processed[$eventId]);
    }
}
