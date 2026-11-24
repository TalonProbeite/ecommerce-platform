<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use App\Infrastructure\Messaging\Topology;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;
use Throwable;

final class CatalogEventsConsumerRabbitMqTest extends PostgresTestCase
{
    protected function setUp(): void
    {
        if (! filter_var(getenv('RUN_RABBITMQ_TESTS'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Set RUN_RABBITMQ_TESTS=1 to run RabbitMQ integration tests.');
        }

        parent::setUp();
    }

    #[Test]
    public function a_product_created_event_is_stored_as_a_snapshot(): void
    {
        $productId = '11111111-1111-4111-8111-111111111111';
        $eventId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $this->publishProductCreated($productId, $eventId);

        $this->artisan('messaging:consume-catalog-events', ['--max-messages' => 1])
            ->assertExitCode(0);

        $row = DB::table('product_snapshots')->where('product_id', $productId)->first();

        self::assertNotNull($row);
        self::assertSame('Drill', $row->name);
        self::assertSame(1500, (int) $row->price_per_hour);
        self::assertSame(5000, (int) $row->deposit_amount);
        self::assertTrue((bool) $row->is_active);
        self::assertDatabaseHas('processed_events', ['event_id' => $eventId]);
    }

    private function publishProductCreated(string $productId, string $eventId): void
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
                'product_id' => $productId,
                'store_id' => '22222222-2222-4222-8222-222222222222',
                'category_id' => '33333333-3333-4333-8333-333333333333',
                'name' => 'Drill',
                'price_per_hour' => 1500,
                'deposit_amount' => 5000,
                'is_active' => true,
            ], JSON_THROW_ON_ERROR);

            $channel->basic_publish(
                new AMQPMessage($body, [
                    'content_type' => 'application/json',
                    'message_id' => $eventId,
                ]),
                (string) config('inbound.exchange'),
                InboundRoutingKeys::PRODUCT_CREATED,
            );
        } finally {
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
    }
}
