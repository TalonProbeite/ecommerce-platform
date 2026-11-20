<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Application\Customer\CreateOrder\CreateOrderCommand;
use App\Application\Customer\CreateOrder\CreateOrderItemCommand;
use App\Application\Customer\CreateOrder\CreateOrderUseCase;
use App\Domain\Catalog\ProductSnapshot;
use App\Domain\Money\Money;
use App\Domain\Order\OrderStatus;
use App\Domain\Pricing\PriceCalculator;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use App\Infrastructure\Messaging\Outbox\OutboxRepository;
use App\Infrastructure\Messaging\Outbox\OutboxWriter;
use App\Infrastructure\Messaging\Topology;
use App\Infrastructure\Persistence\EloquentOrderRepository;
use App\Infrastructure\Persistence\EloquentProductSnapshotRepository;
use App\Infrastructure\Persistence\OrderMapper;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;
use Tests\Support\Fakes\FakeIdempotencyLock;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\SequenceIdGenerator;
use Throwable;

final class OnStockReservedRabbitMqTest extends PostgresTestCase
{
    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';
    private const string PRODUCT_ID = '33333333-3333-4333-8333-333333333333';
    private const string CATEGORY_ID = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        if (! filter_var(getenv('RUN_RABBITMQ_TESTS'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Set RUN_RABBITMQ_TESTS=1 to run RabbitMQ integration tests.');
        }

        parent::setUp();
    }

    #[Test]
    public function a_stock_reserved_event_moves_a_real_order_to_stock_reserved(): void
    {
        $clock = new FixedClock(CarbonImmutable::parse('2026-11-20T10:00:00.000000+00:00'));
        $orderRepository = new EloquentOrderRepository(
            new DbTransactionRunner(),
            new OutboxWriter(new OutboxRepository()),
            new OrderMapper($clock),
            $clock,
        );

        $snapshots = new EloquentProductSnapshotRepository($clock);
        $snapshots->save(new ProductSnapshot(
            productId: self::PRODUCT_ID,
            storeId: self::STORE_ID,
            categoryId: self::CATEGORY_ID,
            name: 'Drill',
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
            isActive: true,
            sourceOccurredAt: $clock->now(),
        ));

        $createOrder = new CreateOrderUseCase(
            $orderRepository,
            $snapshots,
            new FakeIdempotencyLock(),
            new DbTransactionRunner(),
            new SequenceIdGenerator(),
            $clock,
            new PriceCalculator(),
        );

        $result = $createOrder->execute(new CreateOrderCommand(
            self::CUSTOMER_ID,
            'rabbitmq-stock-reserved',
            [new CreateOrderItemCommand(
                self::PRODUCT_ID,
                1,
                CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
                CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
            )],
        ));

        $orderId = $result->order->id();
        $eventId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $this->publishStockReserved($orderId, $eventId);

        $this->artisan('messaging:consume-catalog-events', ['--max-messages' => 1])
            ->assertExitCode(0);

        $row = DB::table('orders')->where('id', $orderId)->first();

        self::assertNotNull($row);
        self::assertSame(OrderStatus::STOCK_RESERVED->value, $row->status);
        self::assertDatabaseHas('processed_events', ['event_id' => $eventId]);
    }

    private function publishStockReserved(string $orderId, string $eventId): void
    {
        $connection = new AMQPStreamConnection(
            (string) config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            (string) config('rabbitmq.user'),
            (string) config('rabbitmq.password'),
            (string) config('rabbitmq.vhost'),
            connection_timeout: (float) config('rabbitmq.connection_timeout'),
            read_write_timeout: (float) config('rabbitmq.read_write_timeout'),
            keepalive: true,
            heartbeat: (int) config('rabbitmq.heartbeat'),
            channel_rpc_timeout: (float) config('rabbitmq.channel_rpc_timeout'),
        );

        try {
            $channel = $connection->channel();
            Topology::declareInbound($channel);
            $channel->queue_purge((string) config('inbound.queue'));

            $body = (string) json_encode([
                'event_id' => $eventId,
                'schema_version' => 1,
                'occurred_at' => '2026-11-20T10:00:00.123456+00:00',
                'order_id' => $orderId,
                'store_id' => self::STORE_ID,
            ], JSON_THROW_ON_ERROR);

            $channel->basic_publish(
                new AMQPMessage($body, [
                    'content_type' => 'application/json',
                    'message_id' => $eventId,
                ]),
                (string) config('inbound.exchange'),
                InboundRoutingKeys::STOCK_RESERVED,
            );
        } finally {
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
    }
}
