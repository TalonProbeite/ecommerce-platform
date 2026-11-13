<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Contracts\Database\TransactionRunnerInterface;
use App\Database\DbTransactionRunner;
use App\Enums\ReservationStatus;
use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Messaging\Inbound\Data\OrderCreatedItemData;
use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use App\Outbox\OutboxRepository;
use App\Outbox\OutboxWriter;
use App\Repositories\ProcessedEventRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Services\Inbound\OrderFulfilmentService;
use App\Services\Inbound\OrderStockReservationService;
use App\Services\Inbound\Results\ProcessingResult;
use App\Services\Inbound\Results\ProcessingStatus;
use App\Services\Inbound\Results\StockRejectionReason;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\PostgresTestCase;

final class InboundServicesTest extends PostgresTestCase
{
    private Store $store;
    private Product $product;
    private Stock $stock;
    private OrderStockReservationService $reservationService;
    private OrderFulfilmentService $fulfilmentService;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tools']);
        $category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 100,
        ]);
        $this->stock = Stock::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 5,
            'reserved' => 0,
        ]);

        $stockRepository = new StockRepository();
        $reservationRepository = new StockReservationRepository();
        $processedEvents = new ProcessedEventRepository();
        $transactions = new DbTransactionRunner();
        $outbox = new OutboxWriter(new OutboxRepository());

        $this->reservationService = new OrderStockReservationService(
            $stockRepository,
            $reservationRepository,
            $processedEvents,
            $transactions,
            $outbox,
        );
        $this->fulfilmentService = new OrderFulfilmentService(
            $stockRepository,
            $reservationRepository,
            $processedEvents,
            $transactions,
            $outbox,
        );
        $this->now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');
    }

    #[Test]
    public function created_issued_returned_updates_the_real_stock_counter(): void
    {
        $lineId = (string) Uuid::uuid7();

        $created = $this->reservationService->handleCreated($this->created($lineId, 2), $this->now);
        self::assertApplied($created);
        self::assertSame(2, $this->stock->fresh()->reserved);
        self::assertSame(ReservationStatus::Reserved->value, $this->reservation($lineId)->status);

        $issued = $this->fulfilmentService->handleIssued($this->issued($lineId, 2), $this->now->addHour());
        self::assertApplied($issued);
        self::assertSame(2, $this->stock->fresh()->reserved);
        $reservation = $this->reservation($lineId);
        self::assertSame(ReservationStatus::Issued->value, $reservation->status);
        self::assertNotNull($reservation->issued_at);

        $returned = $this->fulfilmentService->handleReturned($this->returned($lineId, 2), $this->now->addDays(2));
        self::assertApplied($returned);
        self::assertSame(0, $this->stock->fresh()->reserved);
        $reservation = $this->reservation($lineId);
        self::assertSame(ReservationStatus::Returned->value, $reservation->status);
        self::assertNotNull($reservation->returned_at);
        self::assertSame(3, DB::table('processed_events')->count());
    }

    #[Test]
    public function the_same_created_event_twice_does_not_double_reserve(): void
    {
        $lineId = (string) Uuid::uuid7();
        $event = $this->created($lineId, 2);

        self::assertApplied($this->reservationService->handleCreated($event, $this->now));
        $second = $this->reservationService->handleCreated($event, $this->now);

        self::assertSame(ProcessingStatus::Duplicate, $second->status);
        self::assertSame(2, $this->stock->fresh()->reserved);
        self::assertSame(1, StockReservation::query()->where('order_line_id', $lineId)->count());
        self::assertSame(1, DB::table('processed_events')->count());
    }

    #[Test]
    public function a_rejection_commits_the_inbox_mark_but_writes_no_reservation(): void
    {
        $this->stock->update(['quantity' => 1]);
        $lineId = (string) Uuid::uuid7();
        $event = $this->created($lineId, 2);

        $result = $this->reservationService->handleCreated($event, $this->now);

        self::assertSame(ProcessingStatus::Rejected, $result->status);
        self::assertSame(StockRejectionReason::InsufficientStock, $result->reason);
        self::assertSame(0, $this->stock->fresh()->reserved);
        self::assertSame(0, StockReservation::query()->where('order_line_id', $lineId)->count());
        self::assertSame(1, DB::table('processed_events')->count());

        // The rejection is remembered, so a redelivery is a duplicate instead of a second rejection.
        $redelivery = $this->reservationService->handleCreated($event, $this->now);
        self::assertSame(ProcessingStatus::Duplicate, $redelivery->status);
    }

    #[Test]
    public function a_cancelled_line_is_not_reserved_by_a_late_created_event(): void
    {
        $lineId = (string) Uuid::uuid7();

        $cancelled = $this->reservationService->handleCancelled(
            new \App\Messaging\Inbound\Data\OrderCancelledData(
                eventId: (string) Uuid::uuid7(),
                occurredAt: $this->now,
                orderId: (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $lineId),
                storeId: $this->store->id,
                items: [new OrderLineData($this->product->id, 2, $lineId)],
            ),
            $this->now,
        );
        self::assertApplied($cancelled);
        self::assertSame(0, $this->stock->fresh()->reserved);
        self::assertSame(ReservationStatus::Released->value, $this->reservation($lineId)->status);

        $created = $this->reservationService->handleCreated($this->created($lineId, 2), $this->now->addMinute());
        self::assertApplied($created);
        self::assertSame(0, $this->stock->fresh()->reserved);
        self::assertSame(ReservationStatus::Released->value, $this->reservation($lineId)->status);
    }

    #[Test]
    #[Group('concurrency')]
    public function two_concurrent_reservations_of_the_last_unit_leave_one_applied_and_one_rejected(): void
    {
        self::markTestSkipped('Requires two connections and controlled reservation interleaving before enabling.');
    }

    #[Test]
    public function successful_handle_created_writes_outbox_in_same_transaction_and_rolls_back_with_it(): void
    {
        $lineId = (string) Uuid::uuid7();
        $data = $this->created($lineId, 2);

        $outboxCountBefore = DB::table('outbox')->count();

        // When handleCreated runs successfully, outbox rows are written in the same transaction
        $result = $this->reservationService->handleCreated($data, $this->now);
        self::assertApplied($result);
        // 1 stock.reserved + 1 stock.changed = 2 outbox rows
        self::assertSame($outboxCountBefore + 2, DB::table('outbox')->count());

        // When the transaction rolls back, outbox rows are rolled back too:
        $fakeFailingRunner = new class () implements TransactionRunnerInterface {
            public function run(Closure $callback): mixed
            {
                DB::beginTransaction();
                try {
                    $callback();
                    throw new RuntimeException('Simulated failure triggering rollback');
                } finally {
                    DB::rollBack();
                }
            }
        };

        $failingService = new OrderStockReservationService(
            new StockRepository(),
            new StockReservationRepository(),
            new ProcessedEventRepository(),
            $fakeFailingRunner,
            new OutboxWriter(new OutboxRepository()),
        );

        $lineId2 = (string) Uuid::uuid7();
        $data2 = $this->created($lineId2, 1);
        $outboxCountBeforeRollback = DB::table('outbox')->count();

        try {
            $failingService->handleCreated($data2, $this->now);
            self::fail('Expected exception');
        } catch (RuntimeException) {
            // Expected
        }

        self::assertSame($outboxCountBeforeRollback, DB::table('outbox')->count());
    }

    #[Test]
    public function duplicate_handle_created_with_same_event_id_does_not_add_outbox_rows(): void
    {
        $lineId = (string) Uuid::uuid7();
        $data = $this->created($lineId, 2);

        $this->reservationService->handleCreated($data, $this->now);
        $outboxCount = DB::table('outbox')->count();

        // Duplicate call with same eventId
        $duplicateResult = $this->reservationService->handleCreated($data, $this->now);
        self::assertSame(ProcessingStatus::Duplicate, $duplicateResult->status);
        self::assertSame($outboxCount, DB::table('outbox')->count());
    }

    #[Test]
    public function return_before_issue_retries_without_committing_changes(): void
    {
        $lineId = (string) Uuid::uuid7();
        $this->reservationService->handleCreated($this->created($lineId, 2), $this->now);
        $count = DB::table('processed_events')->count();
        try {
            $this->fulfilmentService->handleReturned($this->returned($lineId, 2), $this->now);
            self::fail('Expected retry');
        } catch (\App\Messaging\Inbound\Exceptions\RetryableMessageException) {
            self::assertSame($count, DB::table('processed_events')->count());
            self::assertSame(2, $this->stock->fresh()->reserved);
            self::assertSame('reserved', $this->reservation($lineId)->status);
        }
    }

    #[Test]
    public function lifecycle_events_reject_each_identity_mismatch_before_transition(): void
    {
        foreach (['order_id', 'store_id', 'product_id'] as $field) {
            foreach (['issued', 'returned', 'cancelled'] as $operation) {
                $lineId = (string) Uuid::uuid7();
                $this->stock->update(['quantity' => 100]);
                $this->reservationService->handleCreated($this->created($lineId, 1), $this->now);
                $event = $operation === 'issued' ? $this->issued($lineId, 1) : $this->returned($lineId, 1);
                $values = ['order_id' => $event->orderId, 'store_id' => $event->storeId, 'product_id' => $this->product->id];
                $values[$field] = (string) Uuid::uuid7();
                $items = [new OrderLineData($values['product_id'], 1, $lineId)];
                $class = match ($operation) {
                    'issued' => OrderIssuedData::class,
                    'returned' => OrderReturnedData::class,
                    'cancelled' => \App\Messaging\Inbound\Data\OrderCancelledData::class,
                };
                $event = new $class((string) Uuid::uuid7(), $this->now, $values['order_id'], $values['store_id'], $items);
                $before = $this->stock->fresh()->reserved;
                try {
                    match ($operation) {
                        'issued' => $this->fulfilmentService->handleIssued($event, $this->now),
                        'returned' => $this->fulfilmentService->handleReturned($event, $this->now),
                        'cancelled' => $this->reservationService->handleCancelled($event, $this->now),
                    };
                    self::fail('Expected identity rejection');
                } catch (\App\Messaging\Inbound\Exceptions\InvalidMessageException) {
                    self::assertSame('reserved', $this->reservation($lineId)->status);
                    self::assertSame($before, $this->stock->fresh()->reserved);
                    self::assertFalse(DB::table('processed_events')->where('event_id', $event->eventId)->exists());
                }
            }
        }
    }

    #[Test]
    public function partial_reservation_insert_rolls_back_stock_and_outbox(): void
    {
        $reservations = new class () extends StockReservationRepository {
            public function insertIgnoringExisting(array $rows): int
            {
                parent::insertIgnoringExisting($rows);
                return 0;
            }
        };
        $service = new OrderStockReservationService(
            new StockRepository(),
            $reservations,
            new ProcessedEventRepository(),
            new DbTransactionRunner(),
            new OutboxWriter(new OutboxRepository())
        );
        try {
            $service->handleCreated($this->created((string) Uuid::uuid7(), 2), $this->now);
            self::fail('Expected retry');
        } catch (\App\Messaging\Inbound\Exceptions\RetryableMessageException) {
            self::assertSame(0, $this->stock->fresh()->reserved);
            self::assertSame(0, DB::table('stock_reservations')->count());
            self::assertSame(0, DB::table('processed_events')->count());
            self::assertSame(0, DB::table('outbox')->count());
        }
    }

    private function created(string $lineId, int $quantity): OrderCreatedData
    {
        return new OrderCreatedData(
            eventId: (string) Uuid::uuid7(),
            occurredAt: $this->now,
            orderId: (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $lineId),
            storeId: $this->store->id,
            items: [
                new OrderCreatedItemData(
                    productId: $this->product->id,
                    quantity: $quantity,
                    rentalStartAt: $this->now->addDay(),
                    rentalEndAt: $this->now->addDays(3),
                    lineId: $lineId,
                ),
            ],
        );
    }

    private function issued(string $lineId, int $quantity): OrderIssuedData
    {
        return new OrderIssuedData(
            eventId: (string) Uuid::uuid7(),
            occurredAt: $this->now,
            orderId: (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $lineId),
            storeId: $this->store->id,
            items: [new OrderLineData($this->product->id, $quantity, $lineId)],
        );
    }

    private function returned(string $lineId, int $quantity): OrderReturnedData
    {
        return new OrderReturnedData(
            eventId: (string) Uuid::uuid7(),
            occurredAt: $this->now,
            orderId: (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $lineId),
            storeId: $this->store->id,
            items: [new OrderLineData($this->product->id, $quantity, $lineId)],
        );
    }

    private function reservation(string $lineId): StockReservation
    {
        return StockReservation::query()->where('order_line_id', $lineId)->firstOrFail();
    }

    private static function assertApplied(ProcessingResult $result): void
    {
        self::assertSame(ProcessingStatus::Applied, $result->status);
        self::assertNull($result->reason);
    }
}
