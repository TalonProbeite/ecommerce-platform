<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Messaging\Inbound\InboundRoutingKeys;
use App\Messaging\Inbound\Topology;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;
use Throwable;

#[Group('rabbitmq')]
final class ConsumeOrdersCommandTest extends PostgresTestCase
{
    private Store $store;
    private Product $product;
    private Stock $stock;
    private CarbonImmutable $now;
    private ?AMQPStreamConnection $connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('RUN_RABBITMQ_TESTS')) {
            self::markTestSkipped('RabbitMQ suite is opt-in: set RUN_RABBITMQ_TESTS=1.');
        }

        $this->now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');

        $this->declareFreshTopology();

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
    }

    protected function tearDown(): void
    {
        try {
            if ($this->connection?->isConnected()) {
                $channel = $this->connection->channel();
                $channel->queue_purge($this->mainQueue());
                $channel->queue_purge($this->deadQueue());
                $channel->close();
            }
        } catch (Throwable) {
        }

        try {
            $this->connection?->close();
        } catch (Throwable) {
        }

        $this->connection = null;

        parent::tearDown();
    }

    #[Test]
    public function topology_declare_is_idempotent(): void
    {
        $channel = $this->connection()->channel();

        Topology::declare($channel);
        Topology::declare($channel);

        $channel->close();

        self::assertSame(0, $this->queueCount($this->mainQueue()));
    }

    #[Test]
    public function order_created_reserves_stock_and_writes_stock_reserved_to_the_outbox(): void
    {
        $lineId = (string) Uuid::uuid7();

        $this->publish(InboundRoutingKeys::ORDER_CREATED, $this->createdPayload($lineId, 2));

        $this->artisan('messaging:consume-orders', ['--max-messages' => 1])->assertSuccessful();

        self::assertSame(1, StockReservation::query()->where('order_line_id', $lineId)->count());
        self::assertSame(2, $this->stock->fresh()?->reserved);
        self::assertSame(
            1,
            DB::table('outbox')->where('routing_key', 'stock.reserved')->count(),
        );
        self::assertSame(0, $this->queueCount($this->mainQueue()));
    }

    #[Test]
    public function an_invalid_payload_is_dead_lettered(): void
    {
        $this->publish(InboundRoutingKeys::ORDER_CREATED, [
            'event_id' => 'not-a-uuid',
        ]);

        $this->artisan('messaging:consume-orders', ['--max-messages' => 1])->assertSuccessful();

        self::assertSame(1, $this->waitForQueueCount($this->deadQueue(), 1));
        self::assertSame(0, $this->waitForQueueCount($this->mainQueue(), 0));
    }

    #[Test]
    public function an_unbound_routing_key_is_not_delivered(): void
    {
        $this->publish('order.completed', $this->createdPayload((string) Uuid::uuid7(), 1));

        self::assertSame(0, $this->queueCount($this->mainQueue()));
        self::assertSame(0, $this->queueCount($this->deadQueue()));
    }

    #[Test]
    public function redelivering_the_same_event_id_is_a_duplicate_and_does_not_double_reserve(): void
    {
        $lineId = (string) Uuid::uuid7();
        $payload = $this->createdPayload($lineId, 2);

        $this->publish(InboundRoutingKeys::ORDER_CREATED, $payload);
        $this->publish(InboundRoutingKeys::ORDER_CREATED, $payload);

        $this->artisan('messaging:consume-orders', ['--max-messages' => 2])->assertSuccessful();

        self::assertSame(1, StockReservation::query()->where('order_line_id', $lineId)->count());
        self::assertSame(2, $this->stock->fresh()?->reserved);
        self::assertSame(
            1,
            DB::table('outbox')->where('routing_key', 'stock.reserved')->count(),
        );
    }

    #[Test]
    #[Group('slow')]
    public function a_retryable_event_is_requeued_until_the_delivery_limit_dead_letters_it(): void
    {
        $this->publish(InboundRoutingKeys::ORDER_ISSUED, $this->issuedPayload((string) Uuid::uuid7()));

        $maxAttempts = (int) config('inbound.delivery_limit') + 5;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($this->waitForQueueCountAtLeast($this->deadQueue(), 1, 1500) > 0) {
                break;
            }

            if ($this->waitForQueueCountAtLeast($this->mainQueue(), 1, 1500) === 0) {
                break;
            }

            $this->artisan('messaging:consume-orders', ['--max-messages' => 1])->assertSuccessful();
        }

        self::assertSame(1, $this->waitForQueueCount($this->deadQueue(), 1));
        self::assertSame(0, $this->waitForQueueCount($this->mainQueue(), 0));
    }

    private function waitForQueueCount(string $queue, int $expected, int $timeoutMs = 3000): int
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $count = $this->queueCount($queue);

        while ($count !== $expected && microtime(true) < $deadline) {
            usleep(50000);
            $count = $this->queueCount($queue);
        }

        return $count;
    }

    private function waitForQueueCountAtLeast(string $queue, int $minimum, int $timeoutMs = 3000): int
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $count = $this->queueCount($queue);

        while ($count < $minimum && microtime(true) < $deadline) {
            usleep(50000);
            $count = $this->queueCount($queue);
        }

        return $count;
    }

    private function declareFreshTopology(): void
    {
        $channel = $this->connection()->channel();

        Topology::declare($channel);

        $channel->queue_purge($this->mainQueue());
        $channel->queue_purge($this->deadQueue());

        $channel->close();
    }

    private function publish(string $routingKey, array $payload): void
    {
        $channel = $this->connection()->channel();

        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => (string) ($payload['event_id'] ?? ''),
            ],
        );

        $channel->basic_publish($message, (string) config('inbound.exchange'), $routingKey);
        $channel->close();
    }

    private function queueCount(string $queue): int
    {
        $channel = $this->connection()->channel();

        /** @var array{0: string, 1: int, 2: int}|null $result */
        $result = $channel->queue_declare($queue, true);
        $channel->close();

        return (int) ($result[1] ?? 0);
    }

    private function connection(): AMQPStreamConnection
    {
        if ($this->connection?->isConnected()) {
            return $this->connection;
        }

        $this->connection = new AMQPStreamConnection(
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

        return $this->connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function createdPayload(string $lineId, int $quantity): array
    {
        return [
            'event_id' => (string) Uuid::uuid7(),
            'occurred_at' => $this->now->toIso8601String(),
            'order_id' => (string) Uuid::uuid7(),
            'store_id' => $this->store->id,
            'items' => [[
                'line_id' => $lineId,
                'product_id' => $this->product->id,
                'quantity' => $quantity,
                'rental_start_at' => $this->now->addDay()->toIso8601String(),
                'rental_end_at' => $this->now->addDays(3)->toIso8601String(),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issuedPayload(string $lineId): array
    {
        return [
            'event_id' => (string) Uuid::uuid7(),
            'occurred_at' => $this->now->toIso8601String(),
            'order_id' => (string) Uuid::uuid7(),
            'store_id' => $this->store->id,
            'items' => [[
                'line_id' => $lineId,
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
        ];
    }

    private function mainQueue(): string
    {
        return (string) config('inbound.queue');
    }

    private function deadQueue(): string
    {
        return (string) config('inbound.dead_queue');
    }
}
