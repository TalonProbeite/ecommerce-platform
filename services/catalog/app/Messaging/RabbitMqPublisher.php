<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Contracts\Messaging\EventInterface;
use App\Contracts\Messaging\EventPublisherInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMqPublisher implements EventPublisherInterface
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function publish(EventInterface $event): void
    {
        $channel = $this->getChannel();

        $message = new AMQPMessage(
            json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ],
        );

        try {
            $channel->basic_publish(
                $message,
                config('rabbitmq.exchange.name'),
                $event->routingKey(),
            );

            $channel->wait_for_pending_acks(5.0);
        } catch (Throwable $e) {
            $this->disconnect();
            throw $e;
        }
    }

    private function getChannel(): AMQPChannel
    {
        if (
            $this->connection?->isConnected()
            && $this->channel?->is_open()
        ) {
            return $this->channel;
        }

        $this->disconnect();

        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
        );

        $this->channel = $this->connection->channel();

        $this->declareExchange($this->channel);

        $this->channel->confirm_select();

        return $this->channel;
    }

    private function declareExchange(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            config('rabbitmq.exchange.name'),
            'topic',
            false,
            true,
            false,
        );
    }

    private function disconnect(): void
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
    }
}
