<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
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

            $exchange = (string) config('rabbitmq.exchange.name');

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
        $channel->wait_for_pending_acks_returns((float) config('rabbitmq.confirm_timeout'));

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

        $channel = $connection->channel();

        $channel->exchange_declare(
            (string) config('rabbitmq.exchange.name'),
            (string) config('rabbitmq.exchange.type'),
            false,
            (bool) config('rabbitmq.exchange.durable'),
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
