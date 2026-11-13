<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Data;

use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\InboundRules;
use App\Messaging\Inbound\PayloadValidator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

final readonly class OrderCreatedData
{
    /**
     * @param list<OrderCreatedItemData> $items
     */
    public function __construct(
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
    public static function fromPayload(array $payload): self
    {
        $data = PayloadValidator::validate(
            $payload,
            [
                'event_id' => InboundRules::EVENT_ID,
                'occurred_at' => InboundRules::TIMESTAMP,
                'order_id' => InboundRules::UUID,
                'store_id' => InboundRules::UUID,
                'items' => InboundRules::items(),
                'items.*.product_id' => InboundRules::UUID,
                'items.*.quantity' => InboundRules::QUANTITY,
                'items.*.rental_start_at' => InboundRules::TIMESTAMP,
                'items.*.rental_end_at' => InboundRules::TIMESTAMP,
            ],
            static function (Validator $validator) use ($payload): void {
                $items = $payload['items'] ?? [];

                if (! is_array($items)) {
                    return;
                }

                foreach ($items as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $startKey = "items.{$index}.rental_start_at";
                    $endKey = "items.{$index}.rental_end_at";

                    // Skip the window check when either date already failed validation.
                    if ($validator->errors()->has($startKey) || $validator->errors()->has($endKey)) {
                        continue;
                    }

                    $start = CarbonImmutable::parse((string) $item['rental_start_at']);
                    $end = CarbonImmutable::parse((string) $item['rental_end_at']);

                    if ($end->lessThanOrEqualTo($start)) {
                        $validator->errors()->add($endKey, 'rental_end_at must be later than rental_start_at.');
                    }
                }
            },
        );

        /** @var list<array<array-key, mixed>> $items */
        $items = $data['items'];

        return new self(
            eventId: (string) $data['event_id'],
            occurredAt: CarbonImmutable::parse((string) $data['occurred_at']),
            orderId: (string) $data['order_id'],
            storeId: (string) $data['store_id'],
            items: array_map(OrderCreatedItemData::fromArray(...), $items),
        );
    }
}
