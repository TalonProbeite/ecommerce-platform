<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Data;

use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\InboundRules;
use App\Messaging\Inbound\PayloadValidator;
use Carbon\CarbonImmutable;

/**
 * Shared shape of order.cancelled, order.issued and order.returned.
 * Subclasses exist only to give each routing key its own type.
 */
abstract readonly class OrderLifecycleData
{
    /**
     * @param list<OrderLineData> $items
     */
    final public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $orderId,
        public string $storeId,
        public array $items,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidMessageException
     */
    public static function fromPayload(array $payload): static
    {
        $data = PayloadValidator::validate($payload, [
            'event_id' => InboundRules::EVENT_ID,
            'occurred_at' => InboundRules::TIMESTAMP,
            'order_id' => InboundRules::UUID,
            'store_id' => InboundRules::UUID,
            'items' => InboundRules::items(),
            'items.*.line_id' => InboundRules::LINE_ID,
            'items.*.product_id' => InboundRules::UUID,
            'items.*.quantity' => InboundRules::QUANTITY,
        ]);

        /** @var list<array<array-key, mixed>> $items */
        $items = $data['items'];

        return new static(
            eventId: (string) $data['event_id'],
            occurredAt: CarbonImmutable::parse((string) $data['occurred_at']),
            orderId: (string) $data['order_id'],
            storeId: (string) $data['store_id'],
            items: array_map(OrderLineData::fromArray(...), $items),
        );
    }
}
