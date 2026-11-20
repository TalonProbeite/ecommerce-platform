<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Application\Saga\Commands\StockReservedCommand;
use App\Application\Saga\OnStockReserved;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Messaging\Outbox\OutboxRepository;
use App\Infrastructure\Messaging\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\EloquentOrderRepository;
use App\Infrastructure\Persistence\EloquentProcessedEventStore;
use App\Infrastructure\Persistence\OrderMapper;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\PostgresTestCase;
use Tests\Support\Fakes\FixedClock;
use Tests\Unit\Domain\OrderFixtures;

final class OnStockReservedPostgresTest extends PostgresTestCase
{
    use OrderFixtures;

    private const string EVENT_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private FixedClock $clock;
    private EloquentOrderRepository $orders;
    private OnStockReserved $useCase;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T10:05:00.000000+00:00');
        $this->clock = new FixedClock($this->now);
        $this->orders = new EloquentOrderRepository(
            new DbTransactionRunner(),
            new OutboxWriter(new OutboxRepository()),
            new OrderMapper($this->clock),
            $this->clock,
        );

        $this->useCase = new OnStockReserved(
            new DbTransactionRunner(),
            new EloquentProcessedEventStore(),
            $this->orders,
            new NullLogger(),
        );
    }

    #[Test]
    public function it_moves_the_order_and_writes_an_order_event_row(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);

        $order = DB::table('orders')->where('id', self::ORDER_ID)->first();

        self::assertNotNull($order);
        self::assertSame(OrderStatus::STOCK_RESERVED->value, $order->status);
        self::assertSame(2, (int) $order->version);

        $events = DB::table('order_events')
            ->where('order_id', self::ORDER_ID)
            ->orderBy('id')
            ->get(['from_status', 'to_status']);

        self::assertCount(2, $events);
        self::assertSame([null, 'created'], [$events[0]->from_status, $events[0]->to_status]);
        self::assertSame(['created', 'stock_reserved'], [$events[1]->from_status, $events[1]->to_status]);
        self::assertDatabaseHas('processed_events', ['event_id' => self::EVENT_ID]);
        self::assertSame(1, DB::table('outbox')->count());
    }

    #[Test]
    public function a_repeated_event_id_does_not_change_any_rows(): void
    {
        $this->seedOrder();

        $this->useCase->handle($this->data(), $this->now);

        $before = DB::table('orders')->where('id', self::ORDER_ID)->first();
        $eventCountBefore = DB::table('order_events')->where('order_id', self::ORDER_ID)->count();
        $outboxCountBefore = DB::table('outbox')->count();

        $this->useCase->handle($this->data(), $this->now);

        $after = DB::table('orders')->where('id', self::ORDER_ID)->first();

        self::assertNotNull($before);
        self::assertNotNull($after);
        self::assertSame($before->status, $after->status);
        self::assertSame((int) $before->version, (int) $after->version);
        self::assertSame((string) $before->updated_at, (string) $after->updated_at);
        self::assertSame($eventCountBefore, DB::table('order_events')->where('order_id', self::ORDER_ID)->count());
        self::assertSame($outboxCountBefore, DB::table('outbox')->count());
        self::assertSame(1, DB::table('processed_events')->where('event_id', self::EVENT_ID)->count());
    }

    private function seedOrder(): void
    {
        $order = $this->makeOrder();
        $this->orders->save($order);
    }

    private function data(): StockReservedCommand
    {
        return new StockReservedCommand(
            eventId: self::EVENT_ID,
            occurredAt: $this->now,
            orderId: self::ORDER_ID,
            storeId: self::STORE_ID,
        );
    }
}
