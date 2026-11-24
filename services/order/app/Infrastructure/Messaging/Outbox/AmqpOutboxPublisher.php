<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Outbox;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

final class AmqpOutboxPublisher implements OutboxMessagePublisherInterface
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private bool $nackReceived = false;
    private ?string $returnedError = null;

    /**
     * @param array<string, mixed> $body
     * @throws Throwable
     */
    public function publish(string $routingKey, array $body, string $messageId): void
    {
        $message = new AMQPMessage(
            json_encode($body, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $messageId,
            ],
        );

        $this->nackReceived = false;
        $this->returnedError = null;

        try {
            $channel = $this->getChannel();

            $exchange = (string) config('rabbitmq.exchange.name', 'order.events');

            $channel->basic_publish(
                $message,
                $exchange,
                $routingKey,
                mandatory: true,
            );

            $this->waitForConfirms($channel, $messageId);
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    /**
     * @phpstan-impure
     */
    private function waitForConfirms(AMQPChannel $channel, string $messageId): void
    {
        $channel->wait_for_pending_acks_returns((float) config('rabbitmq.confirm_timeout', 5.0));

        if ($this->nackReceived) {
            throw new RuntimeException(sprintf(
                'RabbitMQ rejected the published message (nack received for message_id: %s).',
                $messageId,
            ));
        }

        if ($this->returnedError !== null) {
            throw new RuntimeException($this->returnedError);
        }
    }

    /**
     * Get or create an active AMQP channel with publisher confirms.
     */
    private function getChannel(): AMQPChannel
    {
        if (
            $this->connection?->isConnected()
            && $this->channel?->is_open()
        ) {
            return $this->channel;
        }

        $this->close();

        $connection = new AMQPStreamConnection(
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

        $channel = $connection->channel();

        $channel->exchange_declare(
            (string) config('rabbitmq.exchange.name', 'order.events'),
            (string) config('rabbitmq.exchange.type', 'topic'),
            false,
            (bool) config('rabbitmq.exchange.durable', true),
            false,
        );

        $channel->confirm_select();

        $channel->set_nack_handler(function (AMQPMessage $message): void {
            $this->nackReceived = true;
        });

        $channel->set_return_listener(function (
            int $replyCode,
            string $replyText,
            string $exchange,
            string $routingKey,
            AMQPMessage $message,
        ): void {
            $this->returnedError = sprintf(
                'RabbitMQ returned an unroutable message (%d: %s, exchange: %s, routing key: %s).',
                $replyCode,
                $replyText,
                $exchange,
                $routingKey,
            );
        });

        $this->connection = $connection;
        $this->channel = $channel;

        return $channel;
    }

    /**
     * Close the AMQP channel and connection.
     */
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
        $this->nackReceived = false;
        $this->returnedError = null;
    }
}
