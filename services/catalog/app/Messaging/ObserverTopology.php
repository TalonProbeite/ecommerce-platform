<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Contracts\Messaging\AmqpConnectionFactoryInterface;
use App\Contracts\Messaging\ObserverTopologyInterface;

final readonly class ObserverTopology implements ObserverTopologyInterface
{
    private const QUEUE = 'catalog.events.observer';

    public function __construct(
        private AmqpConnectionFactoryInterface $connections,
    ) {
    }

    public function queueName(): string
    {
        return self::QUEUE;
    }

    public function exchangeName(): string
    {
        return (string) config('rabbitmq.exchange.name');
    }

    public function declare(): void
    {
        $connection = $this->connections->connect();

        try {
            $channel = $connection->channel();

            $channel->exchange_declare(
                $this->exchangeName(),
                (string) config('rabbitmq.exchange.type'),
                false,
                (bool) config('rabbitmq.exchange.durable'),
                false,
            );

            $channel->queue_declare(
                self::QUEUE,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            $channel->queue_bind(self::QUEUE, $this->exchangeName(), '#');

            $channel->close();
        } finally {
            $connection->close();
        }
    }
}
