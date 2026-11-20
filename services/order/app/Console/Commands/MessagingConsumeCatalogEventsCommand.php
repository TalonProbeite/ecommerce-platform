<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Messaging\AmqpConnectionFactoryInterface;
use App\Infrastructure\Messaging\Inbound\InboundMessageRouterInterface;
use App\Infrastructure\Messaging\Inbound\InboundOutcome;
use App\Infrastructure\Messaging\Topology;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Consumes catalog product events for Order.
 *
 * Ordering risk: two consumer processes can handle messages of the same
 * product in a different order. The use case keeps a per-snapshot
 * source_occurred_at guard, but no other cross-process ordering protection
 * exists. Run a single consumer instance per environment; this command never
 * processes more than one message concurrently (prefetch = 1).
 */
final class MessagingConsumeCatalogEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messaging:consume-catalog-events
                            {--max-messages= : Stop after processing the given number of messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume inbound catalog events from RabbitMQ and apply them to Order';

    private bool $shouldStop = false;

    public function handle(
        InboundMessageRouterInterface $router,
        AmqpConnectionFactoryInterface $connections,
        LoggerInterface $logger,
    ): int {
        $this->registerSignalHandlers($logger);

        $maxMessages = $this->maxMessages();
        $processed = 0;

        $queue = (string) config('inbound.queue');
        $prefetch = (int) config('inbound.prefetch', 1);
        $retryDelayMs = (int) config('inbound.retry_delay_ms', 500);
        $reconnectDelayMs = (int) config('inbound.reconnect_delay_ms', 1000);
        $waitTimeout = (float) config('inbound.wait_timeout', 1.0);

        while (! $this->shouldStop && ! $this->limitReached($maxMessages, $processed)) {
            $connection = null;
            $channel = null;

            try {
                $connection = $connections->connect();
                $channel = $connection->channel();

                Topology::declareInbound($channel);
                $channel->basic_qos(0, $prefetch, false);

                $channel->basic_consume(
                    $queue,
                    '',
                    false,
                    false,
                    false,
                    false,
                    function (AMQPMessage $message) use (
                        $router,
                        $retryDelayMs,
                        &$processed,
                    ): void {
                        $outcome = $router->dispatch($message);
                        match ($outcome) {
                            InboundOutcome::Ack => $message->ack(),
                            InboundOutcome::Reject => $message->reject(false),
                            InboundOutcome::Retry => $this->retryLater($message, $retryDelayMs),
                        };

                        $processed++;
                        app()->forgetScopedInstances();
                    },
                );

                while ($channel->is_consuming()) {
                    if ($this->shouldStop || $this->limitReached($maxMessages, $processed)) {
                        break;
                    }

                    try {
                        $channel->wait(null, false, $waitTimeout);
                    } catch (AMQPTimeoutException) {
                        continue;
                    }
                }
            } catch (Throwable $exception) {
                if ($this->shouldStop) {
                    break;
                }

                $logger->error('Inbound consumer connection failed; reconnecting.', [
                    'queue' => $queue,
                    'exception' => $exception,
                ]);

                if ($reconnectDelayMs > 0) {
                    usleep($reconnectDelayMs * 1000);
                }
            } finally {
                $this->close($channel, $connection);
            }
        }

        return self::SUCCESS;
    }

    private function maxMessages(): ?int
    {
        $value = $this->option('max-messages');

        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function limitReached(?int $maxMessages, int $processed): bool
    {
        return $maxMessages !== null && $processed >= $maxMessages;
    }

    private function retryLater(AMQPMessage $message, int $delayMs): void
    {
        // basic.reject (not basic.nack) increments the quorum-queue delivery count on
        // RabbitMQ 4.3+, so x-delivery-limit can eventually dead-letter a poison message.
        // On RabbitMQ 3.x both methods count towards the limit, so this remains safe there.
        $message->reject(true);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function close(?AMQPChannel $channel, ?AMQPStreamConnection $connection): void
    {
        try {
            $channel?->close();
        } catch (Throwable) {
        }

        try {
            $connection?->close();
        } catch (Throwable) {
        }
    }

    private function registerSignalHandlers(LoggerInterface $logger): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () use ($logger): void {
            $logger->info('Received SIGTERM; stopping inbound consumer after the current message.');
            $this->shouldStop = true;
        });

        pcntl_signal(SIGINT, function () use ($logger): void {
            $logger->info('Received SIGINT; stopping inbound consumer after the current message.');
            $this->shouldStop = true;
        });
    }
}
