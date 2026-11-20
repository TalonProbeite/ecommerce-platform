<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StockPayloadDataTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredReservedFields(): iterable
    {
        yield 'event_id' => ['event_id'];
        yield 'schema_version' => ['schema_version'];
        yield 'occurred_at' => ['occurred_at'];
        yield 'order_id' => ['order_id'];
        yield 'store_id' => ['store_id'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredFailedFields(): iterable
    {
        yield 'event_id' => ['event_id'];
        yield 'schema_version' => ['schema_version'];
        yield 'occurred_at' => ['occurred_at'];
        yield 'order_id' => ['order_id'];
        yield 'store_id' => ['store_id'];
        yield 'reason' => ['reason'];
    }

    #[Test]
    #[DataProvider('requiredReservedFields')]
    public function stock_reserved_requires_each_field(string $field): void
    {
        $payload = $this->reservedPayload();
        unset($payload[$field]);

        $this->expectException(InvalidMessageException::class);

        StockReservedData::fromPayload($payload);
    }

    #[Test]
    #[DataProvider('requiredFailedFields')]
    public function stock_reservation_failed_requires_each_field(string $field): void
    {
        $payload = $this->failedPayload();
        unset($payload[$field]);

        $this->expectException(InvalidMessageException::class);

        StockReservationFailedData::fromPayload($payload);
    }

    #[Test]
    public function stock_reserved_rejects_a_schema_version_other_than_one(): void
    {
        $this->expectException(InvalidMessageException::class);

        StockReservedData::fromPayload($this->reservedPayload(['schema_version' => 2]));
    }

    #[Test]
    public function stock_reservation_failed_rejects_a_schema_version_other_than_one(): void
    {
        $this->expectException(InvalidMessageException::class);

        StockReservationFailedData::fromPayload($this->failedPayload(['schema_version' => 2]));
    }

    #[Test]
    public function stock_reservation_failed_rejects_an_unknown_reason(): void
    {
        $this->expectException(InvalidMessageException::class);

        StockReservationFailedData::fromPayload($this->failedPayload(['reason' => 'warehouse_on_fire']));
    }

    #[Test]
    public function stock_reserved_maps_all_fields(): void
    {
        $dto = StockReservedData::fromPayload($this->reservedPayload());

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $dto->eventId);
        self::assertSame('2026-11-20T10:00:00.123456+00:00', $dto->occurredAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $dto->orderId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $dto->storeId);
    }

    #[Test]
    public function stock_reservation_failed_maps_all_fields(): void
    {
        $dto = StockReservationFailedData::fromPayload($this->failedPayload());

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $dto->eventId);
        self::assertSame('2026-11-20T10:00:00.123456+00:00', $dto->occurredAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $dto->orderId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $dto->storeId);
        self::assertSame('insufficient_stock', $dto->reason);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function reservedPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'schema_version' => 1,
            'occurred_at' => '2026-11-20T10:00:00.123456+00:00',
            'order_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'store_id' => '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function failedPayload(array $overrides = []): array
    {
        return array_merge($this->reservedPayload(), [
            'reason' => 'insufficient_stock',
        ], $overrides);
    }
}
