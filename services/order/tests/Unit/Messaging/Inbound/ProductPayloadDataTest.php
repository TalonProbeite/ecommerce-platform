<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Data\ProductDeletedData;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductPayloadDataTest extends TestCase
{
    #[Test]
    public function it_rejects_a_schema_version_other_than_one(): void
    {
        $this->expectException(InvalidMessageException::class);

        ProductUpsertData::fromPayload($this->upsertPayload(['schema_version' => 2]));
    }

    #[Test]
    public function it_requires_schema_version(): void
    {
        $payload = $this->upsertPayload();
        unset($payload['schema_version']);

        $this->expectException(InvalidMessageException::class);

        ProductUpsertData::fromPayload($payload);
    }

    #[Test]
    public function it_rejects_a_z_timestamp_suffix(): void
    {
        $this->expectException(InvalidMessageException::class);

        ProductUpsertData::fromPayload($this->upsertPayload([
            'occurred_at' => '2026-11-20T10:00:00.123456Z',
        ]));
    }

    #[Test]
    public function it_accepts_a_timestamp_with_microseconds(): void
    {
        $dto = ProductUpsertData::fromPayload($this->upsertPayload([
            'occurred_at' => '2026-11-20T10:00:00.123456+00:00',
        ]));

        self::assertSame('2026-11-20T10:00:00.123456+00:00', $dto->occurredAt->format('Y-m-d\TH:i:s.uP'));
    }

    #[Test]
    public function it_accepts_a_timestamp_without_microseconds(): void
    {
        $dto = ProductUpsertData::fromPayload($this->upsertPayload([
            'occurred_at' => '2026-11-20T10:00:00+00:00',
        ]));

        self::assertSame('2026-11-20T10:00:00.000000+00:00', $dto->occurredAt->format('Y-m-d\TH:i:s.uP'));
    }

    #[Test]
    public function it_maps_all_product_upsert_fields(): void
    {
        $dto = ProductUpsertData::fromPayload($this->upsertPayload());

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $dto->eventId);
        self::assertSame('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', $dto->productId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $dto->storeId);
        self::assertSame('a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223', $dto->categoryId);
        self::assertSame('Drill', $dto->name);
        self::assertSame(1500, $dto->pricePerHour);
        self::assertSame(5000, $dto->depositAmount);
        self::assertTrue($dto->isActive);
    }

    #[Test]
    public function it_maps_all_product_deleted_fields(): void
    {
        $dto = ProductDeletedData::fromPayload([
            'event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'schema_version' => 1,
            'occurred_at' => '2026-11-20T10:00:00.123456+00:00',
            'product_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            'store_id' => '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f',
        ]);

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $dto->eventId);
        self::assertSame('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', $dto->productId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $dto->storeId);
    }

    #[Test]
    public function it_rejects_a_negative_price(): void
    {
        $this->expectException(InvalidMessageException::class);

        ProductUpsertData::fromPayload($this->upsertPayload(['price_per_hour' => -1]));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function upsertPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'schema_version' => 1,
            'occurred_at' => '2026-11-20T10:00:00.123456+00:00',
            'product_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            'store_id' => '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f',
            'category_id' => 'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223',
            'name' => 'Drill',
            'price_per_hour' => 1500,
            'deposit_amount' => 5000,
            'is_active' => true,
        ], $overrides);
    }
}
