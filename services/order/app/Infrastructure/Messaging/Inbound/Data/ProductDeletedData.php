<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Data;

use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundRules;
use App\Infrastructure\Messaging\Inbound\PayloadValidator;
use Carbon\CarbonImmutable;

final readonly class ProductDeletedData
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $productId,
        public string $storeId,
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
                'schema_version' => InboundRules::SCHEMA_VERSION,
                'occurred_at' => InboundRules::TIMESTAMP,
                'product_id' => InboundRules::PRODUCT_ID,
                'store_id' => InboundRules::STORE_ID,
            ],
        );

        return new self(
            eventId: (string) $data['event_id'],
            occurredAt: CarbonImmutable::parse((string) $data['occurred_at']),
            productId: (string) $data['product_id'],
            storeId: (string) $data['store_id'],
        );
    }
}
