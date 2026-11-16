<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Application\Saga\OnStockReservationFailed;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\Handlers\StockReservationFailedHandler;
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

final class StockReservationFailedHandlerTest extends TestCase
{
    use OrderFixtures;

    private const string EVENT_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    private FakeOutboxWriter $outbox;
    private InMemoryOrderRepository $orders;
    private RecordingLogger $logger;
    private StockReservationFailedHandler $handler;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T10:05:00.000000+00:00');
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, new FixedClock($this->now));
        $this->logger = new RecordingLogger();

        $useCase = new OnStockReservationFailed(
            new FakeTransactionRunner(),
            new InMemoryProcessedEventStore(),
            $this->orders,
            $this->logger,
        );

        $this->handler = new StockReservationFailedHandler($useCase, $this->logger);
    }

    #[Test]
    public function a_missing_order_becomes_an_invalid_message(): void
    {
        try {
            $this->handler->handle($this->dto(), $this->now);

            self::fail('Expected InvalidMessageException.');
        } catch (InvalidMessageException $e) {
            self::assertSame(['order_id' => ['The order does not exist.']], $e->errors);
            self::assertTrue($this->logger->hasLevel('error'));
        }
    }

    #[Test]
    public function a_store_mismatch_becomes_an_invalid_message(): void
    {
        $this->orders->save($this->makeOrder());
        $this->orders->resetJournal();
        $this->outbox->reset();

        try {
            $this->handler->handle(
                $this->dto(storeId: '99999999-9999-4999-8999-999999999999'),
                $this->now,
            );

            self::fail('Expected InvalidMessageException.');
        } catch (InvalidMessageException $e) {
            self::assertSame(['store_id' => ['The event store does not match the order store.']], $e->errors);
            self::assertTrue($this->logger->hasLevel('error'));
        }

        $order = $this->orders->findById(self::ORDER_ID);

        self::assertNotNull($order);
        self::assertSame(OrderStatus::CREATED, $order->status());
        self::assertCount(0, $this->orders->journal());
        self::assertCount(0, $this->outbox->events());
    }

    private function dto(
        string $orderId = self::ORDER_ID,
        string $storeId = self::STORE_ID,
    ): StockReservationFailedData {
        return new StockReservationFailedData(
            eventId: self::EVENT_ID,
            occurredAt: $this->now,
            orderId: $orderId,
            storeId: $storeId,
            reason: 'insufficient_stock',
        );
    }
}
