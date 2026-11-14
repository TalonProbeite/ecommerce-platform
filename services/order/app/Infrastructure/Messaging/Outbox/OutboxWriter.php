<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

use App\Domain\Contracts\EventInterface;
use App\Infrastructure\Messaging\Routing\OutboundRoutingKeys;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

final readonly class OutboxWriter implements OutboxWriterInterface
{
    public function __construct(
        private OutboxRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
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
        $field = match ($routingKey) {
            OutboundRoutingKeys::ORDER_CREATED,
            OutboundRoutingKeys::ORDER_CANCELLED,
            OutboundRoutingKeys::ORDER_ISSUED,
            OutboundRoutingKeys::ORDER_RETURNED,
            OutboundRoutingKeys::ORDER_PAID => 'order_id',
            default => null,
        };

        if ($field === null) {
            $this->logger?->warning('Cannot determine an aggregate key for the outbound event.', [
                'routing_key' => $routingKey,
            ]);

            return null;
        }

        if (! isset($payload[$field]) || $payload[$field] === '') {
            $this->logger?->warning('The outbound event payload is missing its aggregate key field.', [
                'routing_key' => $routingKey,
                'field' => $field,
            ]);

            return null;
        }

        return (string) $payload[$field];
    }
}
