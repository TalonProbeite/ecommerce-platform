<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inbound;

use App\Enums\ReservationStatus;
use App\Messaging\Events\StockChanged;
use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Messaging\Inbound\Exceptions\InvalidReservationTransitionException;
use App\Messaging\Inbound\Exceptions\ReservationMismatchException;
use App\Messaging\Inbound\Exceptions\ReservationNotFoundException;
use App\Messaging\Inbound\Exceptions\RetryableMessageException;
use App\Services\Inbound\OrderFulfilmentService;
use App\Services\Inbound\Results\ProcessingResult;
use App\Services\Inbound\Results\ProcessingStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeProcessedEventRepository;
use Tests\Support\Fakes\FakeStockRepository;
use Tests\Support\Fakes\FakeStockReservationRepository;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\TestCase;

final class OrderFulfilmentServiceTest extends TestCase
{
    private const EVENT_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    private const ORDER_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const STORE_ID = '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f';
    private const PRODUCT_ID = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    private const LINE_ID = 'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223';

    private FakeStockRepository $stocks;
    private FakeStockReservationRepository $reservations;
    private FakeOutboxWriter $outbox;
    private OrderFulfilmentService $service;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stocks = new FakeStockRepository();
        $this->reservations = new FakeStockReservationRepository();
        $this->outbox = new FakeOutboxWriter();
        $this->now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');

        $this->service = new OrderFulfilmentService(
            $this->stocks,
            $this->reservations,
            new FakeProcessedEventRepository(),
            new FakeTransactionRunner(),
            $this->outbox,
        );
    }

    #[Test]
    public function it_issues_a_reserved_line_without_changing_stock_counters(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2);

        $result = $this->service->handleIssued($this->issued(), $this->now);

        self::assertApplied($result);
        self::assertSame(2, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        $reservation = $this->reservations->get(self::LINE_ID);
        self::assertSame(ReservationStatus::Issued->value, $reservation?->status);
        self::assertTrue($reservation?->issued_at?->equalTo($this->now));

        // Issued: nothing published
        self::assertSame(0, $this->outbox->count());
    }

    #[Test]
    public function it_requires_a_reservation_when_issuing(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);

        $this->expectException(ReservationNotFoundException::class);

        $this->service->handleIssued($this->issued(), $this->now);
    }

    #[Test]
    public function it_returns_an_issued_line_and_releases_reserved_stock(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Issued);

        $result = $this->service->handleReturned($this->returned(), $this->now);

        self::assertApplied($result);
        self::assertSame([self::PRODUCT_ID], $result->affectedProductIds);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        $reservation = $this->reservations->get(self::LINE_ID);
        self::assertSame(ReservationStatus::Returned->value, $reservation?->status);
        self::assertTrue($reservation?->returned_at?->equalTo($this->now));

        // Returned: stock.changed for each affected product
        self::assertSame(1, $this->outbox->count());
        $changedEvents = $this->outbox->eventsOf(StockChanged::class);
        self::assertCount(1, $changedEvents);
        self::assertSame(self::PRODUCT_ID, $changedEvents[0]->productId);
        self::assertSame(self::STORE_ID, $changedEvents[0]->storeId);
        self::assertSame(10, $changedEvents[0]->quantity);
        self::assertSame(0, $changedEvents[0]->reserved);
    }

    #[Test]
    public function a_return_before_issue_is_retried_instead_of_rejected(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Reserved);

        $this->expectException(RetryableMessageException::class);
        $this->expectExceptionMessage('issued');

        $this->service->handleReturned($this->returned(), $this->now);
    }

    #[Test]
    public function a_return_of_a_released_line_is_permanently_rejected(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Released);

        $this->expectException(InvalidReservationTransitionException::class);

        $this->service->handleReturned($this->returned(), $this->now);
    }

    #[Test]
    public function it_rejects_issued_and_returned_events_that_do_not_match_the_reservation_identity(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10, 2);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2);

        $mismatches = [
            [new OrderLineData('11111111-1111-4111-8111-111111111111', 2, self::LINE_ID), 'product_id'],
            [new OrderLineData(self::PRODUCT_ID, 3, self::LINE_ID), 'quantity'],
        ];

        foreach ($mismatches as [$line, $field]) {
            foreach (['issued', 'returned'] as $operation) {
                $eventId = (string) Uuid::uuid7();

                try {
                    if ($operation === 'issued') {
                        $this->service->handleIssued(
                            new OrderIssuedData($eventId, $this->now, self::ORDER_ID, self::STORE_ID, [$line]),
                            $this->now,
                        );
                    } else {
                        $this->service->handleReturned(
                            new OrderReturnedData($eventId, $this->now, self::ORDER_ID, self::STORE_ID, [$line]),
                            $this->now,
                        );
                    }

                    self::fail('Expected an identity mismatch rejection.');
                } catch (ReservationMismatchException $exception) {
                    self::assertContains($field, $exception->fields);
                    self::assertSame(ReservationStatus::Reserved->value, $this->reservations->get(self::LINE_ID)?->status);
                    self::assertSame(2, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
                    self::assertSame(0, $this->outbox->count());
                }
            }
        }
    }

    #[Test]
    public function a_second_return_is_a_no_op(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);
        $this->reservations->seed(self::LINE_ID, self::ORDER_ID, self::PRODUCT_ID, self::STORE_ID, 2, ReservationStatus::Returned);

        $result = $this->service->handleReturned($this->returned(), $this->now);

        self::assertApplied($result);
        self::assertSame([], $result->affectedProductIds);
        self::assertSame(0, $this->stocks->get(self::PRODUCT_ID, self::STORE_ID)?->reserved);
        self::assertSame(0, $this->outbox->count());
    }

    #[Test]
    public function it_requires_a_reservation_when_returning(): void
    {
        $this->stocks->seed(self::PRODUCT_ID, self::STORE_ID, 10);

        $this->expectException(ReservationNotFoundException::class);

        $this->service->handleReturned($this->returned(), $this->now);
    }

    private function issued(): OrderIssuedData
    {
        return new OrderIssuedData(
            eventId: self::EVENT_ID,
            occurredAt: $this->now,
            orderId: self::ORDER_ID,
            storeId: self::STORE_ID,
            items: [$this->line()],
        );
    }

    private function returned(): OrderReturnedData
    {
        return new OrderReturnedData(
            eventId: self::EVENT_ID,
            occurredAt: $this->now,
            orderId: self::ORDER_ID,
            storeId: self::STORE_ID,
            items: [$this->line()],
        );
    }

    private function line(): OrderLineData
    {
        return new OrderLineData(self::PRODUCT_ID, 2, self::LINE_ID);
    }

    private static function assertApplied(ProcessingResult $result): void
    {
        self::assertSame(ProcessingStatus::Applied, $result->status);
        self::assertNull($result->reason);
    }
}
