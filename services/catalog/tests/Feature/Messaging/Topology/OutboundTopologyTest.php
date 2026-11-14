<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Topology;

use App\Messaging\OutboundTopology;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OutboundTopologyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('RUN_RABBITMQ_TESTS')) {
            self::markTestSkipped('Set RUN_RABBITMQ_TESTS=1 with a running RabbitMQ to run topology tests.');
        }
    }

    #[Test]
    public function declaring_the_exchange_twice_is_idempotent_and_creates_a_topic_exchange(): void
    {
        $topology = new OutboundTopology();

        $topology->declare();
        $topology->declare();

        $connection = $this->connection();
        $channel = $connection->channel();
        $exchange = (string) config('rabbitmq.exchange.name');

        try {
            $channel->exchange_declare($exchange, 'topic', true, true, false);

            try {
                $channel->exchange_declare($exchange, 'direct', false, true, false);
                self::fail('Declaring the exchange with a different type must fail.');
            } catch (AMQPProtocolChannelException $exception) {
                self::assertStringContainsString('PRECONDITION_FAILED', $exception->getMessage());
            }
        } finally {
            $channel->close();
            $connection->close();
        }

        $this->addToAssertionCount(1);
    }

    private function connection(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
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
    }
}
