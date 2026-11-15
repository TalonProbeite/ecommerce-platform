<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\SyncProductSnapshot\SyncProductSnapshotUseCase;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Infrastructure\Messaging\Inbound\Data\ProductDeletedData;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\InMemoryProcessedEventStore;
use Tests\Support\Fakes\InMemoryProductSnapshotRepository;
use Tests\TestCase;

final class SyncProductSnapshotUseCaseTest extends TestCase
{
    private const PRODUCT_ID = '11111111-1111-4111-8111-111111111111';
    private const STORE_ID = '22222222-2222-4222-8222-222222222222';
    private const CATEGORY_ID = '33333333-3333-4333-8333-333333333333';

    private FakeTransactionRunner $transactions;
    private InMemoryProductSnapshotRepository $snapshots;
    private InMemoryProcessedEventStore $processedEvents;
    private SyncProductSnapshotUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new FakeTransactionRunner();
        $this->snapshots = new InMemoryProductSnapshotRepository();
        $this->processedEvents = new InMemoryProcessedEventStore();

        $this->useCase = new SyncProductSnapshotUseCase(
            $this->transactions,
            $this->snapshots,
            $this->processedEvents,
            new NullLogger(),
        );
    }

    #[Test]
    public function it_creates_a_snapshot_when_none_exists(): void
    {
        $occurredAt = $this->time('2026-11-20T10:00:00.123456+00:00');

        $this->useCase->upsert($this->upsert('event-1', $occurredAt), $this->time('2026-11-20T10:01:00+00:00'));

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertSame('Drill', $snapshot->name);
        self::assertSame(1500, $snapshot->pricePerHour->amount());
        self::assertSame(5000, $snapshot->depositPerUnit->amount());
        self::assertTrue($snapshot->isActive);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($occurredAt));
        self::assertNull($snapshot->deletedAt);
        self::assertTrue($this->transactions->wasCalled);
        self::assertTrue($this->processedEvents->wasProcessed('event-1'));
    }

    #[Test]
    public function it_updates_an_existing_snapshot_with_a_newer_event(): void
    {
        $this->snapshots->add($this->snapshot($this->time('2026-11-20T09:00:00+00:00')));
        $occurredAt = $this->time('2026-11-20T10:00:00+00:00');

        $this->useCase->upsert(
            $this->upsert('event-2', $occurredAt, name: 'Hammer', pricePerHour: 2000, isActive: false),
            $this->time('2026-11-20T10:01:00+00:00'),
            InboundRoutingKeys::PRODUCT_UPDATED,
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertSame('Hammer', $snapshot->name);
        self::assertSame(2000, $snapshot->pricePerHour->amount());
        self::assertFalse($snapshot->isActive);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($occurredAt));
    }

    #[Test]
    public function it_ignores_an_update_older_than_the_snapshot(): void
    {
        $sourceOccurredAt = $this->time('2026-11-20T10:00:00+00:00');
        $this->snapshots->add($this->snapshot($sourceOccurredAt));

        $this->useCase->upsert(
            $this->upsert('event-3', $this->time('2026-11-20T09:00:00+00:00'), name: 'Stale'),
            $this->time('2026-11-20T10:01:00+00:00'),
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertSame('Drill', $snapshot->name);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($sourceOccurredAt));
        self::assertTrue($this->processedEvents->wasProcessed('event-3'));
    }

    #[Test]
    public function it_resurrects_an_update_after_deletion(): void
    {
        $deletedAt = $this->time('2026-11-20T09:30:00+00:00');
        $occurredAt = $this->time('2026-11-20T10:00:00+00:00');
        $this->snapshots->add($this->snapshot($this->time('2026-11-20T09:00:00+00:00'), $deletedAt));

        $this->useCase->upsert(
            $this->upsert('event-4', $occurredAt, name: 'Resurrected'),
            $this->time('2026-11-20T10:01:00+00:00'),
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertSame('Resurrected', $snapshot->name);
        self::assertNull($snapshot->deletedAt);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($occurredAt));
    }

    #[Test]
    public function it_ignores_a_duplicate_event_id(): void
    {
        $occurredAt = $this->time('2026-11-20T10:00:00+00:00');
        $this->useCase->upsert($this->upsert('event-5', $occurredAt), $this->time('2026-11-20T10:01:00+00:00'));

        $this->useCase->upsert(
            $this->upsert('event-5', $this->time('2026-11-20T11:00:00+00:00'), name: 'Duplicate'),
            $this->time('2026-11-20T11:01:00+00:00'),
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertSame('Drill', $snapshot->name);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($occurredAt));
        self::assertCount(1, $this->processedEvents->all());
    }

    #[Test]
    public function it_deletes_an_existing_snapshot_using_the_clock_time(): void
    {
        $sourceOccurredAt = $this->time('2026-11-20T09:00:00+00:00');
        $deletedAt = $this->time('2026-11-20T10:00:00+00:00');
        $occurredAt = $this->time('2026-11-20T09:59:59.999999+00:00');
        $this->snapshots->add($this->snapshot($sourceOccurredAt));

        $this->useCase->delete($this->deleted('event-6', $occurredAt), $deletedAt);

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertNotNull($snapshot->deletedAt);
        self::assertTrue($snapshot->deletedAt->equalTo($deletedAt));
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($occurredAt));
    }

    #[Test]
    public function it_does_nothing_when_deleting_a_missing_snapshot(): void
    {
        $this->useCase->delete(
            $this->deleted('event-7', $this->time('2026-11-20T10:00:00+00:00')),
            $this->time('2026-11-20T10:01:00+00:00'),
        );

        self::assertNull($this->snapshots->findById(self::PRODUCT_ID));
        self::assertTrue($this->processedEvents->wasProcessed('event-7'));
    }

    #[Test]
    public function it_ignores_a_repeated_deletion(): void
    {
        $deletedAt = $this->time('2026-11-20T09:30:00+00:00');
        $this->snapshots->add($this->snapshot($this->time('2026-11-20T09:00:00+00:00'), $deletedAt));

        $this->useCase->delete(
            $this->deleted('event-8', $this->time('2026-11-20T10:00:00+00:00')),
            $this->time('2026-11-20T10:01:00+00:00'),
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertNotNull($snapshot->deletedAt);
        self::assertTrue($snapshot->deletedAt->equalTo($deletedAt));
    }

    #[Test]
    public function it_ignores_a_deletion_older_than_the_snapshot(): void
    {
        $sourceOccurredAt = $this->time('2026-11-20T10:00:00+00:00');
        $this->snapshots->add($this->snapshot($sourceOccurredAt));

        $this->useCase->delete(
            $this->deleted('event-9', $this->time('2026-11-20T09:00:00+00:00')),
            $this->time('2026-11-20T10:01:00+00:00'),
        );

        $snapshot = $this->snapshots->findById(self::PRODUCT_ID);

        self::assertNotNull($snapshot);
        self::assertNull($snapshot->deletedAt);
        self::assertTrue($snapshot->sourceOccurredAt->equalTo($sourceOccurredAt));
    }

    private function upsert(
        string $eventId,
        CarbonImmutable $occurredAt,
        string $name = 'Drill',
        int $pricePerHour = 1500,
        bool $isActive = true,
    ): ProductUpsertData {
        return new ProductUpsertData(
            eventId: $eventId,
            occurredAt: $occurredAt,
            productId: self::PRODUCT_ID,
            storeId: self::STORE_ID,
            categoryId: self::CATEGORY_ID,
            name: $name,
            pricePerHour: $pricePerHour,
            depositAmount: 5000,
            isActive: $isActive,
        );
    }

    private function deleted(string $eventId, CarbonImmutable $occurredAt): ProductDeletedData
    {
        return new ProductDeletedData(
            eventId: $eventId,
            occurredAt: $occurredAt,
            productId: self::PRODUCT_ID,
            storeId: self::STORE_ID,
        );
    }

    private function snapshot(CarbonImmutable $sourceOccurredAt, ?CarbonImmutable $deletedAt = null): ProductSnapshot
    {
        return new ProductSnapshot(
            productId: self::PRODUCT_ID,
            storeId: self::STORE_ID,
            categoryId: self::CATEGORY_ID,
            name: 'Drill',
            pricePerHour: Money::fromMinorUnits(1500),
            depositPerUnit: Money::fromMinorUnits(5000),
            isActive: true,
            sourceOccurredAt: $sourceOccurredAt,
            deletedAt: $deletedAt,
        );
    }

    private function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }
}
