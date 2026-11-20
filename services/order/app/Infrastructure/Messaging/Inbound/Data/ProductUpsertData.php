<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Data;

use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundRules;
use App\Infrastructure\Messaging\Inbound\PayloadValidator;
use Carbon\CarbonImmutable;

final readonly class ProductUpsertData
{
    public function __construct(
        public string $eventId,
        public CarbonImmutable $occurredAt,
        public string $productId,
        public string $storeId,
        public string $categoryId,
        public string $name,
        public int $pricePerHour,
        public int $depositAmount,
        public bool $isActive,
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
                'category_id' => InboundRules::CATEGORY_ID,
                'name' => InboundRules::PRODUCT_NAME,
                'price_per_hour' => InboundRules::PRICE_PER_HOUR,
                'deposit_amount' => InboundRules::DEPOSIT_AMOUNT,
                'is_active' => InboundRules::IS_ACTIVE,
            ],
        );

        return new self(
            eventId: (string) $data['event_id'],
            occurredAt: CarbonImmutable::parse((string) $data['occurred_at']),
            productId: (string) $data['product_id'],
            storeId: (string) $data['store_id'],
            categoryId: (string) $data['category_id'],
            name: (string) $data['name'],
            pricePerHour: (int) $data['price_per_hour'],
            depositAmount: (int) $data['deposit_amount'],
            isActive: (bool) $data['is_active'],
        );
    }
}
