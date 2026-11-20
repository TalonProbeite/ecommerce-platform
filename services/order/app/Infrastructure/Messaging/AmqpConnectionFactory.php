<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class AmqpConnectionFactory implements AmqpConnectionFactoryInterface
{
    public function connect(): AMQPStreamConnection
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
