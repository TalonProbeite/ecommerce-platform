<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\StripeEventStore;
use Carbon\CarbonImmutable;

final class InMemoryStripeEventStore implements StripeEventStore
{
    /**
     * @var array<string, array{type: string, processed_at: CarbonImmutable}>
     */
    private array $processed = [];

    public function markProcessed(string $eventId, string $type, CarbonImmutable $at): bool
    {
        if (isset($this->processed[$eventId])) {
            return false;
        }

        $this->processed[$eventId] = [
            'type' => $type,
            'processed_at' => $at,
        ];

        return true;
    }

    public function wasProcessed(string $eventId): bool
    {
        return isset($this->processed[$eventId]);
    }

    /**
     * @return array<string, array{type: string, processed_at: CarbonImmutable}>
     */
    public function all(): array
    {
        return $this->processed;
    }
}
