<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxRepositoryInterface;
use App\Contracts\Outbox\OutboxWriterInterface;
use App\Domain\Contracts\EventInterface;
use App\Messaging\RoutingKeys;
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
            RoutingKeys::PRODUCT_CREATED,
            RoutingKeys::PRODUCT_UPDATED,
            RoutingKeys::PRODUCT_DELETED,
            RoutingKeys::STOCK_CHANGED => 'product_id',
            RoutingKeys::CATEGORY_CREATED,
            RoutingKeys::CATEGORY_UPDATED,
            RoutingKeys::CATEGORY_DELETED => 'category_id',
            RoutingKeys::STOCK_RESERVED,
            RoutingKeys::STOCK_RESERVATION_FAILED => 'order_id',
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
