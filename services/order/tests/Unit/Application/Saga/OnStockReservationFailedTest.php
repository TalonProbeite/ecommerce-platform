<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Saga;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Application\Saga\Commands\StockReservationFailedCommand;
use App\Application\Saga\OnStockReservationFailed;
use App\Application\Saga\StockRejectionReason;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryProcessedEventStore;
use Tests\Support\Fakes\RecordingLogger;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

final class OnStockReservationFailedTest extends TestCase
{
    use OrderFixtures;

    private const string EVENT_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    private FakeTransactionRunner $transactions;
    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private InMemoryProcessedEventStore $processedEvents;
    private RecordingLogger $logger;
    private OnStockReservationFailed $useCase;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T10:05:00.000000+00:00');
        $this->transactions = new FakeTransactionRunner();
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, new FixedClock($this->now));
        $this->processedEvents = new InMemoryProcessedEventStore();
        $this->logger = new RecordingLogger();

        $this->useCase = new OnStockReservationFailed(
            $this->transactions,
            $this->processedEvents,
            $this->orders,
            $this->logger,
        );
    }

    #[Test]
    public function it_cancels_a_created_order_with_stock_unavailable_and_leaves_the_outbox_empty(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::CANCELLED, $order->status());
        self::assertSame(CancelReason::STOCK_UNAVAILABLE, $order->cancelReason());
        self::assertCount(1, $this->orders->journal());
        self::assertSame(OrderStatus::CREATED, $this->orders->journal()[0]->fromStatus);
        self::assertSame(OrderStatus::CANCELLED, $this->orders->journal()[0]->toStatus);
        self::assertCount(0, $this->outbox->events());
        self::assertTrue($this->processedEvents->wasProcessed(self::EVENT_ID));
    }

    #[Test]
    public function it_does_not_change_a_cancelled_order(): void
    {
        $order = $this->seedOrder();
        $order->cancel(CancelReason::CUSTOMER_REQUEST, ActorRole::CUSTOMER, $this->now);
        $this->orders->save($order);
        $this->orders->resetJournal();
        $this->outbox->reset();

        $this->useCase->handle($this->data(), $this->now);

        $loaded = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::CANCELLED, $loaded->status());
        self::assertSame(CancelReason::CUSTOMER_REQUEST, $loaded->cancelReason());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
    }

    #[Test]
    public function it_is_a_no_op_for_a_paid_order_and_logs_it(): void
    {
        $order = $this->seedOrder();
        $order->markStockReserved($this->now);
        $order->markPaymentPending($this->now);
        $order->markPaid($this->now);
        $this->orders->save($order);
        $this->orders->resetJournal();
        $this->outbox->reset();
        $paidVersion = $order->version();

        $this->useCase->handle($this->data(), $this->now);

        $loaded = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::PAID, $loaded->status());
        self::assertSame($paidVersion, $loaded->version());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
        self::assertTrue($this->logger->hasLevel('warning'));
        self::assertFalse($this->logger->hasLevel('info'));
    }

    #[Test]
    public function it_throws_order_not_found_for_a_missing_order(): void
    {
        $this->expectException(OrderNotFound::class);

        $this->useCase->handle(
            $this->data(orderId: '99999999-9999-4999-8999-999999999999'),
            $this->now,
        );
    }

    #[Test]
    public function it_does_not_change_the_order_or_journal_when_the_store_does_not_match(): void
    {
        $order = $this->seedOrder();

        try {
            $this->useCase->handle(
                $this->data(storeId: '99999999-9999-4999-8999-999999999999'),
                $this->now,
            );

            self::fail('Expected StoreMismatch.');
        } catch (StoreMismatch $e) {
            self::assertSame(self::ORDER_ID, $e->orderId);
            self::assertSame(self::STORE_ID, $e->expectedStoreId);
            self::assertSame('99999999-9999-4999-8999-999999999999', $e->actualStoreId);
        }

        $loaded = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($loaded);
        self::assertSame($order->status(), $loaded->status());
        self::assertSame($order->version(), $loaded->version());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
        self::assertFalse($this->processedEvents->wasProcessed(self::EVENT_ID));
    }

    #[Test]
    public function it_is_a_no_op_for_an_already_processed_event_id(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);
        $this->useCase->handle($this->data(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::CANCELLED, $order->status());
        self::assertSame(2, $order->version());
        self::assertCount(1, $this->orders->journal());
        self::assertCount(1, $this->processedEvents->all());
    }

    private function seedOrder(): Order
    {
        $order = $this->makeOrder();
        $this->orders->save($order);
        $this->orders->resetJournal();
        $this->outbox->reset();

        return $order;
    }

    private function data(
        string $eventId = self::EVENT_ID,
        string $orderId = self::ORDER_ID,
        string $storeId = self::STORE_ID,
    ): StockReservationFailedCommand {
        return new StockReservationFailedCommand(
            eventId: $eventId,
            occurredAt: $this->now,
            orderId: $orderId,
            storeId: $storeId,
            reason: StockRejectionReason::InsufficientStock,
        );
    }
}
