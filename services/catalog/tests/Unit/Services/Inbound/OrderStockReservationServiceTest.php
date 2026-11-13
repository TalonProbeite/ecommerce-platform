<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inbound;

use App\Enums\ReservationStatus;
use App\Messaging\Events\StockChanged;
use App\Messaging\Events\StockReservationFailed;
use App\Messaging\Events\StockReservationFailureReason;
use App\Messaging\Events\StockReserved;
use App\Messaging\Inbound\Data\OrderCancelledData;
use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Messaging\Inbound\Data\OrderCreatedItemData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Exceptions\InvalidReservationTransitionException;
use App\Messaging\Inbound\Exceptions\ReservationMismatchException;
use App\Services\Inbound\OrderStockReservationService;
use App\Services\Inbound\Results\ProcessingResult;
use App\Services\Inbound\Results\ProcessingStatus;
use App\Services\Inbound\Results\StockRejectionReason;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeProcessedEventRepository;
use Tests\Support\Fakes\FakeStockRepository;
use Tests\Support\Fakes\FakeStockReservationRepository;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\TestCase;

final class OrderStockReservationServiceTest extends TestCase
{
    private const EVENT_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    private const ORDER_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const STORE_ID = '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f';
    private const PRODUCT_ID = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    private const LINE_ID = 'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223';

    private FakeStockRepository $stocks;
    private FakeStockReservationRepository $reservations;
    private FakeProcessedEventRepository $processedEvents;
    private FakeOutboxWriter $outbox;
    private OrderStockReservationService $service;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stocks = new FakeStockRepository();
        $this->reservations = new FakeStockReservationRepository();
        $this->processedEvents = new FakeProcessedEventRepository();
        $this->outbox = new FakeOutboxWriter();
        $this->now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');

        $this->service = new OrderStockReservationService(
            $this->stocks,
            $this->reservations,
            $this->processedEvents,
            new FakeTransactionRunner(),
            $this->outbox,
        );
    }

    #[Test]
    public function it_reserves_stock_when_enough_is_available(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);

        $result = $this->service->handleCreated($this->created(), $this->now);

        self::assertApplied($result);
        self::assertSame([self::PRODUCT_ID], $result->affectedProductIds);
        self::assertSame(2, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        $reservation = $this->reservations->get(self::LINE_ID);
        self::assertNotNull($reservation);
        self::assertSame(ReservationStatus::Reserved->value, $reservation->status);
        self::assertSame(2, $reservation->quantity);
        self::assertTrue($reservation->rental_start_at?->equalTo($this->now->addDay()));
        self::assertTrue($reservation->rental_end_at?->equalTo($this->now->addDays(3)));

        // Outbox assertions: exactly one StockReserved and one StockChanged per product
        self::assertSame(2, $this->outbox->count());
        $reservedEvents = $this->outbox->eventsOf(StockReserved::class);
        self::assertCount(1, $reservedEvents);
        self::assertSame(self::ORDER_ID, $reservedEvents[0]->orderId);
        self::assertSame(self::STORE_ID, $reservedEvents[0]->storeId);

        $changedEvents = $this->outbox->eventsOf(StockChanged::class);
        self::assertCount(1, $changedEvents);
        self::assertSame(self::PRODUCT_ID, $changedEvents[0]->productId);
        self::assertSame(self::STORE_ID, $changedEvents[0]->storeId);
        self::assertSame(10, $changedEvents[0]->quantity);
        self::assertSame(2, $changedEvents[0]->reserved);
    }

    #[Test]
    public function it_rejects_the_whole_event_when_one_line_is_not_available(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 9);

        $result = $this->service->handleCreated($this->created(), $this->now);

        self::assertRejected($result, StockRejectionReason::InsufficientStock);
        self::assertSame(9, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertSame([], $this->reservations->all());

        // Outbox assertions: only StockReservationFailed(insufficient_stock), no StockChanged
        self::assertSame(1, $this->outbox->count());
        $failedEvents = $this->outbox->eventsOf(StockReservationFailed::class);
        self::assertCount(1, $failedEvents);
        self::assertSame(self::ORDER_ID, $failedEvents[0]->orderId);
        self::assertSame(self::STORE_ID, $failedEvents[0]->storeId);
        self::assertSame(StockReservationFailureReason::InsufficientStock, $failedEvents[0]->reason);
        self::assertEmpty($this->outbox->eventsOf(StockChanged::class));
    }

    #[Test]
    public function it_sums_quantities_of_the_same_product_before_checking_stock(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 5);

        $result = $this->service->handleCreated(
            $this->created(items: [
                $this->createdItem(self::LINE_ID, 3),
                $this->createdItem('b1b2b3b4-c1c2-4d1d-8e1e-1f2021222324', 3),
            ]),
            $this->now,
        );

        self::assertRejected($result, StockRejectionReason::InsufficientStock);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertSame([], $this->reservations->all());

        self::assertSame(1, $this->outbox->count());
        $failedEvents = $this->outbox->eventsOf(StockReservationFailed::class);
        self::assertCount(1, $failedEvents);
        self::assertSame(StockReservationFailureReason::InsufficientStock, $failedEvents[0]->reason);
        self::assertEmpty($this->outbox->eventsOf(StockChanged::class));
    }

    #[Test]
    public function it_returns_duplicate_and_does_not_change_state_for_the_same_event(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);

        $this->service->handleCreated($this->created(), $this->now);
        self::assertSame(2, $this->outbox->count());

        $this->outbox->reset();
        $result = $this->service->handleCreated($this->created(), $this->now);

        self::assertSame(ProcessingStatus::Duplicate, $result->status);
        self::assertSame(2, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertCount(1, $this->reservations->all());
        // Duplicate event: 0 outbox entries written
        self::assertSame(0, $this->outbox->count());
    }

    #[Test]
    public function it_rejects_when_the_product_has_no_stock_row_in_the_store(): void
    {
        $result = $this->service->handleCreated($this->created(), $this->now);

        self::assertRejected($result, StockRejectionReason::ProductNotFound);
        self::assertSame([], $this->reservations->all());

        self::assertSame(1, $this->outbox->count());
        $failedEvents = $this->outbox->eventsOf(StockReservationFailed::class);
        self::assertCount(1, $failedEvents);
        self::assertSame(StockReservationFailureReason::ProductNotFound, $failedEvents[0]->reason);
        self::assertEmpty($this->outbox->eventsOf(StockChanged::class));
    }

    #[Test]
    public function a_cancel_before_created_creates_a_tombstone_and_created_skips_the_line(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 5);

        $cancelled = $this->service->handleCancelled($this->cancelled(), $this->now);

        self::assertApplied($cancelled);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        $tombstone = $this->reservations->get(self::LINE_ID);
        self::assertNotNull($tombstone);
        self::assertSame(ReservationStatus::Released->value, $tombstone->status);
        self::assertNull($tombstone->rental_start_at);
        // Tombstone: nothing published
        self::assertSame(0, $this->outbox->count());

        $created = $this->service->handleCreated(
            $this->created(eventId: '8c9e6679-7425-40de-944b-e07fc1f90ae7'),
            $this->now->addMinute(),
        );

        self::assertApplied($created);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertCount(1, $this->reservations->all());
        self::assertSame(ReservationStatus::Released->value, $this->reservations->get(self::LINE_ID)?->status);
        // All lines already exist: nothing published
        self::assertSame(0, $this->outbox->count());
    }

    #[Test]
    public function it_handles_mixed_existing_lines_without_reservation_or_outbox_and_logs_warning(): void
    {
        Log::spy();

        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);
        // Pre-seed one line as existing (e.g. from an early partial cancellation/tombstone)
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Released);

        $secondLineId = 'b1b2b3b4-c1c2-4d1d-8e1e-1f2021222324';
        $result = $this->service->handleCreated(
            $this->created(items: [
                $this->createdItem(self::LINE_ID, 2),
                $this->createdItem($secondLineId, 3),
            ]),
            $this->now,
        );

        self::assertApplied($result);
        self::assertSame([], $result->affectedProductIds);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertNull($this->reservations->get($secondLineId));
        self::assertSame(0, $this->outbox->count());

        Log::shouldHaveReceived('warning')->once()->with(
            'Partial existing lines detected for order',
            ['order_id' => self::ORDER_ID, 'event_id' => self::EVENT_ID],
        );
    }

    #[Test]
    public function it_releases_a_reserved_line_on_cancel(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);
        $this->service->handleCreated($this->created(), $this->now);
        $this->outbox->reset();

        $result = $this->service->handleCancelled(
            $this->cancelled(eventId: '8c9e6679-7425-40de-944b-e07fc1f90ae7'),
            $this->now->addMinute(),
        );

        self::assertApplied($result);
        self::assertSame([self::PRODUCT_ID], $result->affectedProductIds);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertSame(ReservationStatus::Released->value, $this->reservations->get(self::LINE_ID)?->status);

        // Cancelled reserved: stock.changed for each affected product, nothing for Order
        self::assertSame(1, $this->outbox->count());
        $changedEvents = $this->outbox->eventsOf(StockChanged::class);
        self::assertCount(1, $changedEvents);
        self::assertSame(self::PRODUCT_ID, $changedEvents[0]->productId);
        self::assertSame(10, $changedEvents[0]->quantity);
        self::assertSame(0, $changedEvents[0]->reserved);
    }

    #[Test]
    public function a_second_cancel_is_a_no_op(): void
    {
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Released);

        $result = $this->service->handleCancelled($this->cancelled(), $this->now);

        self::assertApplied($result);
        self::assertSame([], $result->affectedProductIds);
        self::assertSame(ReservationStatus::Released->value, $this->reservations->get(self::LINE_ID)?->status);
        self::assertSame(0, $this->outbox->count());
    }

    #[Test]
    public function it_rejects_cancelling_an_issued_line(): void
    {
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Issued);
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->service->handleCancelled($this->cancelled(), $this->now);
    }

    #[Test]
    public function it_rejects_cancelling_a_reservation_whose_identity_does_not_match(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2);

        $mismatches = [
            [new OrderLineData('11111111-1111-4111-8111-111111111111', 2, self::LINE_ID), 'product_id'],
            [new OrderLineData(self::PRODUCT_ID, 3, self::LINE_ID), 'quantity'],
        ];

        foreach ($mismatches as [$line, $field]) {
            $event = new OrderCancelledData(
                (string) Uuid::uuid7(),
                $this->now,
                self::ORDER_ID,
                self::STORE_ID,
                [$line],
            );

            try {
                $this->service->handleCancelled($event, $this->now);
                self::fail('Expected an identity mismatch rejection.');
            } catch (ReservationMismatchException $exception) {
                self::assertContains($field, $exception->fields);
                self::assertSame(ReservationStatus::Reserved->value, $this->reservations->get(self::LINE_ID)?->status);
                self::assertSame(2, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
                self::assertSame(0, $this->outbox->count());
            }
        }
    }

    /**
     * @param list<OrderCreatedItemData>|null $items
     */
    private function created(?array $items = null, string $eventId = self::EVENT_ID): OrderCreatedData
    {
        return new OrderCreatedData(
            eventId: $eventId,
            occurredAt: $this->now,
            orderId: self::ORDER_ID,
            storeId: self::STORE_ID,
            items: $items ?? [$this->createdItem(self::LINE_ID, 2)],
        );
    }

    private function createdItem(string $lineId, int $quantity): OrderCreatedItemData
    {
        return new OrderCreatedItemData(
            productId: self::PRODUCT_ID,
            quantity: $quantity,
            rentalStartAt: $this->now->addDay(),
            rentalEndAt: $this->now->addDays(3),
            lineId: $lineId,
        );
    }

    private function cancelled(string $eventId = self::EVENT_ID): OrderCancelledData
    {
        return new OrderCancelledData(
            eventId: $eventId,
            occurredAt: $this->now,
            orderId: self::ORDER_ID,
            storeId: self::STORE_ID,
            items: [new OrderLineData(self::PRODUCT_ID, 2, self::LINE_ID)],
        );
    }

    private static function assertApplied(ProcessingResult $result): void
    {
        self::assertSame(ProcessingStatus::Applied, $result->status);
        self::assertNull($result->reason);
    }

    private static function assertRejected(ProcessingResult $result, StockRejectionReason $reason): void
    {
        self::assertSame(ProcessingStatus::Rejected, $result->status);
        self::assertSame($reason, $result->reason);
    }
}
