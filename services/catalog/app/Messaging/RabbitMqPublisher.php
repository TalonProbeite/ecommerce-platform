<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Contracts\Messaging\EventInterface;
use App\Contracts\Messaging\EventPublisherInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

final class RabbitMqPublisher implements EventPublisherInterface
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private ?float $lastActivityAt = null;

    public function publish(EventInterface $event): void
    {
        $message = new AMQPMessage(
            json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ],
        );

        try {
            $channel = $this->getChannel();

            $channel->basic_publish(
                $message,
                config('rabbitmq.exchange.name'),
                $event->routingKey(),
                mandatory: true,
            );

            $channel->wait_for_pending_acks_returns(config('rabbitmq.confirm_timeout'));
            $this->lastActivityAt = hrtime(true) / 1e9;
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    private function getChannel(): AMQPChannel
    {
        if (
            $this->connection?->isConnected()
            && $this->channel?->is_open()
            && $this->lastActivityAt !== null
            && (hrtime(true) / 1e9) - $this->lastActivityAt < config('rabbitmq.heartbeat') / 2
        ) {
            return $this->channel;
        }

        // HTTP workers do not pump AMQP heartbeats between requests. Reconnect
        // after idle time instead of reusing a connection the broker may have closed.
        $this->close();

        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
            connection_timeout: config('rabbitmq.connection_timeout'),
            read_write_timeout: config('rabbitmq.read_write_timeout'),
            keepalive: true,
            heartbeat: config('rabbitmq.heartbeat'),
            channel_rpc_timeout: config('rabbitmq.channel_rpc_timeout'),
        );

        $this->channel = $this->connection->channel();

        $this->declareExchange($this->channel);

        $this->channel->set_nack_handler(static function (AMQPMessage $message): void {
            throw new RuntimeException('RabbitMQ rejected the published message.');
        });
        $this->channel->set_return_listener(static function (
            int $replyCode,
            string $replyText,
            string $exchange,
            string $routingKey,
            AMQPMessage $message,
        ): void {
            throw new RuntimeException(sprintf(
                'RabbitMQ returned an unroutable message (%d: %s, exchange: %s, routing key: %s).',
                $replyCode,
                $replyText,
                $exchange,
                $routingKey,
            ));
        });
        $this->channel->confirm_select();

        return $this->channel;
    }

    private function declareExchange(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            config('rabbitmq.exchange.name'),
            config('rabbitmq.exchange.type'),
            false,
            config('rabbitmq.exchange.durable'),
            false,
        );
    }

    public function close(): void
    {
        try {
            $this->channel?->close();
        } catch (Throwable) {
        }

        try {
            $this->connection?->close();
        } catch (Throwable) {
        }

        $this->channel = null;
        $this->connection = null;
        $this->lastActivityAt = null;
    }
}
