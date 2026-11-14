<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final class Topology
{
    public const OUTBOUND_EXCHANGE_NAME = 'order.events';
    public const OUTBOUND_EXCHANGE_TYPE = 'topic';
    public const OUTBOUND_EXCHANGE_DURABLE = true;
    public const OUTBOUND_EXCHANGE_AUTO_DELETE = false;
    public const OUTBOUND_EXCHANGE_INTERNAL = false;

    public const INBOUND_EXCHANGE_NAME = 'catalog.events';
    public const INBOUND_EXCHANGE_TYPE = 'topic';
    public const INBOUND_EXCHANGE_DURABLE = true;
    public const INBOUND_EXCHANGE_AUTO_DELETE = false;
    public const INBOUND_EXCHANGE_INTERNAL = false;

    public const INBOUND_QUEUE_NAME = 'order.catalog-events';
    public const INBOUND_QUEUE_TYPE = 'quorum';
    public const INBOUND_QUEUE_DURABLE = true;
    public const INBOUND_QUEUE_AUTO_DELETE = false;
    public const INBOUND_QUEUE_EXCLUSIVE = false;

    public const DLX_EXCHANGE_NAME = 'order.catalog-events.dlx';
    public const DLX_EXCHANGE_TYPE = 'direct';
    public const DLX_EXCHANGE_DURABLE = true;
    public const DLX_EXCHANGE_AUTO_DELETE = false;
    public const DLX_EXCHANGE_INTERNAL = false;

    public const DEAD_QUEUE_NAME = 'order.catalog-events.dead';
    public const DEAD_QUEUE_TYPE = 'quorum';
    public const DEAD_QUEUE_DURABLE = true;
    public const DEAD_QUEUE_AUTO_DELETE = false;
    public const DEAD_QUEUE_EXCLUSIVE = false;

    public const DELIVERY_LIMIT = 5;
    public const DEAD_LETTER_STRATEGY = 'at-least-once';
    public const OVERFLOW = 'reject-publish';

    /**
     * Declare the full topology via an active AMQP channel.
     */
    public static function declareAll(AMQPChannel $channel): void
    {
        self::declareOutbound($channel);
        self::declareInbound($channel);
    }

    /**
     * Declare the outbound exchange order.events.
     */
    public static function declareOutbound(AMQPChannel $channel): void
    {
        $exchange = (string) config('rabbitmq.exchange.name', self::OUTBOUND_EXCHANGE_NAME);
        $type = (string) config('rabbitmq.exchange.type', self::OUTBOUND_EXCHANGE_TYPE);
        $durable = (bool) config('rabbitmq.exchange.durable', self::OUTBOUND_EXCHANGE_DURABLE);

        $channel->exchange_declare(
            $exchange,
            $type,
            false,
            $durable,
            self::OUTBOUND_EXCHANGE_AUTO_DELETE,
            self::OUTBOUND_EXCHANGE_INTERNAL,
        );
    }

    /**
     * Declare the inbound topology: catalog.events exchange, order.catalog-events queue,
     * DLX exchange and dead-letter queue, plus bindings.
     */
    public static function declareInbound(AMQPChannel $channel): void
    {
        $exchange = (string) config('inbound.exchange', self::INBOUND_EXCHANGE_NAME);
        $queue = (string) config('inbound.queue', self::INBOUND_QUEUE_NAME);
        $deadLetterExchange = (string) config('inbound.dlx_exchange', self::DLX_EXCHANGE_NAME);
        $deadQueue = (string) config('inbound.dead_queue', self::DEAD_QUEUE_NAME);
        $deliveryLimit = (int) config('inbound.delivery_limit', self::DELIVERY_LIMIT);

        // 1. Declare inbound catalog.events exchange (identical to Catalog declaration).
        $channel->exchange_declare(
            $exchange,
            self::INBOUND_EXCHANGE_TYPE,
            false,
            self::INBOUND_EXCHANGE_DURABLE,
            self::INBOUND_EXCHANGE_AUTO_DELETE,
            self::INBOUND_EXCHANGE_INTERNAL,
        );

        // 2. Declare dead-letter exchange (direct, durable).
        $channel->exchange_declare(
            $deadLetterExchange,
            self::DLX_EXCHANGE_TYPE,
            false,
            self::DLX_EXCHANGE_DURABLE,
            self::DLX_EXCHANGE_AUTO_DELETE,
            self::DLX_EXCHANGE_INTERNAL,
        );

        // 3. Declare dead-letter queue (quorum, durable).
        $channel->queue_declare(
            $deadQueue,
            false,
            self::DEAD_QUEUE_DURABLE,
            self::DEAD_QUEUE_EXCLUSIVE,
            self::DEAD_QUEUE_AUTO_DELETE,
            false,
            new AMQPTable(['x-queue-type' => self::DEAD_QUEUE_TYPE]),
        );

        // 4. Bind dead-letter queue to DLX exchange using queue name as routing key.
        $channel->queue_bind($deadQueue, $deadLetterExchange, $queue);

        // 5. Declare inbound quorum queue with DLX and at-least-once strategy.
        $channel->queue_declare(
            $queue,
            false,
            self::INBOUND_QUEUE_DURABLE,
            self::INBOUND_QUEUE_EXCLUSIVE,
            self::INBOUND_QUEUE_AUTO_DELETE,
            false,
            new AMQPTable([
                'x-queue-type' => self::INBOUND_QUEUE_TYPE,
                'x-dead-letter-exchange' => $deadLetterExchange,
                'x-dead-letter-routing-key' => $queue,
                'x-delivery-limit' => $deliveryLimit,
                'x-dead-letter-strategy' => self::DEAD_LETTER_STRATEGY,
                'x-overflow' => self::OVERFLOW,
            ]),
        );

        // 6. Bind inbound queue to catalog.events for all subscribed routing keys.
        foreach (InboundRoutingKeys::all() as $routingKey) {
            $channel->queue_bind($queue, $exchange, $routingKey);
        }
    }

    /**
     * Connect to RabbitMQ and declare the outbound exchange. Used by relay and commands.
     */
    public function declare(): void
    {
        $connection = $this->connect();

        try {
            $channel = $connection->channel();
            self::declareOutbound($channel);
            $channel->close();
        } finally {
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
    }

    private function connect(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            (string) config('rabbitmq.host', 'rabbitmq'),
            (int) config('rabbitmq.port', 5672),
            (string) config('rabbitmq.user', 'guest'),
            (string) config('rabbitmq.password', 'guest'),
            (string) config('rabbitmq.vhost', '/'),
            connection_timeout: (float) config('rabbitmq.connection_timeout', 3.0),
            read_write_timeout: (float) config('rabbitmq.read_write_timeout', 25.0),
            keepalive: true,
            heartbeat: (int) config('rabbitmq.heartbeat', 10),
            channel_rpc_timeout: (float) config('rabbitmq.channel_rpc_timeout', 5.0),
        );
    }
}
