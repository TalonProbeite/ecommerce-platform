<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class Topology
{
    public static function declare(AMQPChannel $channel): void
    {
        $exchange = (string) config('inbound.exchange');
        $queue = (string) config('inbound.queue');
        $deadLetterExchange = (string) config('inbound.dlx_exchange');
        $deadQueue = (string) config('inbound.dead_queue');
        $deliveryLimit = (int) config('inbound.delivery_limit');

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->exchange_declare($deadLetterExchange, 'direct', false, true, false);

        $channel->queue_declare(
            $deadQueue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-queue-type' => 'quorum']),
        );

        $channel->queue_bind($deadQueue, $deadLetterExchange, $queue);

        // Existing queues declared without these immutable arguments must be recreated
        // once in development (rabbitmqctl delete_queue or the management UI). The
        // deployment helper scripts/configure-inbound-dead-lettering.py applies an
        // equivalent mutable policy where recreating the queue is not acceptable.
        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-queue-type' => 'quorum',
                'x-dead-letter-exchange' => $deadLetterExchange,
                'x-dead-letter-routing-key' => $queue,
                'x-delivery-limit' => $deliveryLimit,
                'x-dead-letter-strategy' => 'at-least-once',
                'x-overflow' => 'reject-publish',
            ]),
        );

        foreach (InboundRoutingKeys::all() as $routingKey) {
            $channel->queue_bind($queue, $exchange, $routingKey);
        }
    }

    private function __construct()
    {
    }
}
