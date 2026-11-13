<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxRepositoryInterface;
use App\Contracts\Outbox\OutboxWriterInterface;
use App\Domain\Contracts\EventInterface;
use App\Messaging\RoutingKeys;
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
        $routingKey = $event->routingKey();
        $payload = $event->toArray();

        $this->repository->insert(
            eventId: $eventId,
            routingKey: $routingKey,
            payload: $payload,
            occurredAt: $occurredAt,
            aggregateKey: $this->aggregateKey($routingKey, $payload),
        );

        return $eventId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function aggregateKey(string $routingKey, array $payload): ?string
    {
        return match ($routingKey) {
            RoutingKeys::STOCK_RESERVED,
            RoutingKeys::STOCK_RESERVATION_FAILED => isset($payload['order_id']) ? (string) $payload['order_id'] : null,
            RoutingKeys::STOCK_CHANGED => isset($payload['product_id']) ? (string) $payload['product_id'] : null,
            default => null,
        };
    }
}
