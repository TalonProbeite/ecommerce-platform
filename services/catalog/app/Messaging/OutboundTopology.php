<?php

declare(strict_types=1);

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

/**
 * Declares the Catalog outbound exchange. Queue and binding ownership belongs to
 * each consuming service, so this class never creates queues or bindings.
 */
class OutboundTopology
{
    public function declare(): void
    {
        $connection = $this->connect();

        try {
            $channel = $connection->channel();
            self::declareExchange($channel);
            $channel->close();
        } finally {
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
    }

    public static function declareExchange(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            (string) config('rabbitmq.exchange.name'),
            (string) config('rabbitmq.exchange.type'),
            false,
            (bool) config('rabbitmq.exchange.durable'),
            false,
        );
    }

    private function connect(): AMQPStreamConnection
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
