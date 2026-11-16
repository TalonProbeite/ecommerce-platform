<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Saga;

use App\Application\Saga\OnStockReserved;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
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

final class OnStockReservedTest extends TestCase
{
    use OrderFixtures;

    private const string EVENT_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private FakeTransactionRunner $transactions;
    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private InMemoryProcessedEventStore $processedEvents;
    private RecordingLogger $logger;
    private OnStockReserved $useCase;
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

        $this->useCase = new OnStockReserved(
            $this->transactions,
            $this->processedEvents,
            $this->orders,
            $this->logger,
        );
    }

    #[Test]
    public function it_moves_a_created_order_to_stock_reserved_and_writes_one_journal_entry(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::STOCK_RESERVED, $order->status());
        self::assertSame(2, $order->version());
        self::assertCount(1, $this->orders->journal());
        self::assertSame(OrderStatus::CREATED, $this->orders->journal()[0]->fromStatus);
        self::assertSame(OrderStatus::STOCK_RESERVED, $this->orders->journal()[0]->toStatus);
        self::assertCount(0, $this->outbox->events());
        self::assertTrue($this->transactions->wasCalled);
        self::assertTrue($this->processedEvents->wasProcessed(self::EVENT_ID));
    }

    #[Test]
    public function it_does_not_change_a_cancelled_order(): void
    {
        $order = $this->seedOrder();
        $this->cancel($order);

        $this->useCase->handle($this->data(), $this->now);

        $loaded = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::CANCELLED, $loaded->status());
        self::assertSame(CancelReason::CUSTOMER_REQUEST, $loaded->cancelReason());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
    }

    #[Test]
    public function it_is_a_no_op_for_a_repeated_reservation_with_a_different_event_id(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'), $this->now);
        $this->useCase->handle($this->data('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::STOCK_RESERVED, $order->status());
        self::assertSame(2, $order->version());
        self::assertCount(1, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
    }

    #[Test]
    public function it_is_a_no_op_for_an_already_processed_event_id(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);
        $this->useCase->handle($this->data(), $this->now);

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::STOCK_RESERVED, $order->status());
        self::assertSame(2, $order->version());
        self::assertCount(1, $this->orders->journal());
        self::assertCount(1, $this->processedEvents->all());
    }

    #[Test]
    public function it_rejects_a_reservation_for_a_missing_order(): void
    {
        $this->expectException(InvalidMessageException::class);

        try {
            $this->useCase->handle(
                $this->data(orderId: '99999999-9999-4999-8999-999999999999'),
                $this->now,
            );
        } finally {
            self::assertTrue($this->logger->hasLevel('error'));
        }
    }

    #[Test]
    public function it_keeps_a_cancelled_order_cancelled_when_reservation_arrives_after_cancellation(): void
    {
        // Race: the order is cancelled between order.created publication and the
        // stock.reserved delivery. Catalog already released the reservation.
        $order = $this->seedOrder();
        $this->cancel($order);

        $this->useCase->handle($this->data(), $this->now);

        $loaded = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::CANCELLED, $loaded->status());
        self::assertSame($order->version(), $loaded->version());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
    }

    private function seedOrder(): Order
    {
        $order = $this->makeOrder();
        $this->orders->save($order);
        $this->orders->resetJournal();
        $this->outbox->reset();

        return $order;
    }

    private function cancel(Order $order): void
    {
        $order->cancel(CancelReason::CUSTOMER_REQUEST, ActorRole::CUSTOMER, $this->now);
        $this->orders->save($order);
        $this->orders->resetJournal();
        $this->outbox->reset();
    }

    private function data(
        string $eventId = self::EVENT_ID,
        string $orderId = self::ORDER_ID,
    ): StockReservedData {
        return new StockReservedData(
            eventId: $eventId,
            occurredAt: $this->now,
            orderId: $orderId,
            storeId: self::STORE_ID,
        );
    }
}
