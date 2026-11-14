<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\OutboundTopology;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final class MessagingTopologyObserverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messaging:topology:observer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Declare development observer queue catalog.events.observer on catalog.events exchange';

    public function handle(): int
    {
        $queueName = 'catalog.events.observer';
        $exchangeName = (string) config('rabbitmq.exchange.name');

        $this->info(sprintf('Declaring observer queue "%s" on exchange "%s"...', $queueName, $exchangeName));

        try {
            $connection = new AMQPStreamConnection(
                (string) config('rabbitmq.host'),
                (int) config('rabbitmq.port'),
                (string) config('rabbitmq.user'),
                (string) config('rabbitmq.password'),
                (string) config('rabbitmq.vhost'),
                connection_timeout: (float) config('rabbitmq.connection_timeout'),
                read_write_timeout: (float) config('rabbitmq.read_write_timeout'),
            );

            $channel = $connection->channel();

            OutboundTopology::declareExchange($channel);

            $channel->queue_declare(
                $queueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            $channel->queue_bind($queueName, $exchangeName, '#');

            $channel->close();
            $connection->close();

            $this->info(sprintf('Observer queue "%s" bound to "%s" with routing key "#".', $queueName, $exchangeName));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to declare observer topology: %s', $e->getMessage()));

            return self::FAILURE;
        }
    }
}
