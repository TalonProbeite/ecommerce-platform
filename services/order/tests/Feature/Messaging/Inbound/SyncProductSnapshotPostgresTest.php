<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Application\Catalog\SyncProductSnapshot\SyncProductSnapshotUseCase;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use App\Infrastructure\Persistence\EloquentProcessedEventStore;
use App\Infrastructure\Persistence\EloquentProductSnapshotRepository;
use App\Infrastructure\Persistence\SystemClock;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\PostgresTestCase;

final class SyncProductSnapshotPostgresTest extends PostgresTestCase
{
    private const PRODUCT_ID = '11111111-1111-4111-8111-111111111111';
    private const STORE_ID = '22222222-2222-4222-8222-222222222222';
    private const CATEGORY_ID = '33333333-3333-4333-8333-333333333333';
    private const EVENT_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    #[Test]
    public function upsert_writes_a_product_snapshot_row(): void
    {
        $occurredAt = CarbonImmutable::parse('2026-11-20T10:00:00.123456+00:00');

        $this->useCase()->upsert(
            $this->upsertData(self::EVENT_ID, $occurredAt),
            CarbonImmutable::parse('2026-11-20T10:01:00+00:00'),
        );

        $row = DB::table('product_snapshots')->where('product_id', self::PRODUCT_ID)->first();

        self::assertNotNull($row);
        self::assertSame(self::STORE_ID, $row->store_id);
        self::assertSame(self::CATEGORY_ID, $row->category_id);
        self::assertSame('Drill', $row->name);
        self::assertSame(1500, (int) $row->price_per_hour);
        self::assertSame(5000, (int) $row->deposit_amount);
        self::assertTrue((bool) $row->is_active);
        self::assertNull($row->deleted_at);
        self::assertSame(
            $occurredAt->format('Y-m-d H:i:s.uP'),
            CarbonImmutable::parse((string) $row->source_occurred_at)->format('Y-m-d H:i:s.uP'),
        );
        self::assertDatabaseHas('processed_events', ['event_id' => self::EVENT_ID]);
    }

    #[Test]
    public function a_duplicate_event_id_does_not_change_the_snapshot(): void
    {
        $firstOccurredAt = CarbonImmutable::parse('2026-11-20T10:00:00.123456+00:00');
        $useCase = $this->useCase();

        $useCase->upsert(
            $this->upsertData(self::EVENT_ID, $firstOccurredAt),
            CarbonImmutable::parse('2026-11-20T10:01:00+00:00'),
        );

        $useCase->upsert(
            $this->upsertData(
                self::EVENT_ID,
                CarbonImmutable::parse('2026-11-20T11:00:00.123456+00:00'),
                name: 'Duplicate',
            ),
            CarbonImmutable::parse('2026-11-20T11:01:00+00:00'),
            InboundRoutingKeys::PRODUCT_UPDATED,
        );

        $row = DB::table('product_snapshots')->where('product_id', self::PRODUCT_ID)->first();

        self::assertNotNull($row);
        self::assertSame('Drill', $row->name);
        self::assertSame(
            $firstOccurredAt->format('Y-m-d H:i:s.uP'),
            CarbonImmutable::parse((string) $row->source_occurred_at)->format('Y-m-d H:i:s.uP'),
        );
        self::assertSame(1, DB::table('processed_events')->where('event_id', self::EVENT_ID)->count());
    }

    private function useCase(): SyncProductSnapshotUseCase
    {
        return new SyncProductSnapshotUseCase(
            new DbTransactionRunner(),
            new EloquentProductSnapshotRepository(new SystemClock()),
            new EloquentProcessedEventStore(),
            new NullLogger(),
        );
    }

    private function upsertData(
        string $eventId,
        CarbonImmutable $occurredAt,
        string $name = 'Drill',
    ): ProductUpsertData {
        return new ProductUpsertData(
            eventId: $eventId,
            occurredAt: $occurredAt,
            productId: self::PRODUCT_ID,
            storeId: self::STORE_ID,
            categoryId: self::CATEGORY_ID,
            name: $name,
            pricePerHour: 1500,
            depositAmount: 5000,
            isActive: true,
        );
    }
}
