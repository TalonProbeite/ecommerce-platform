<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Outbox\OutboxRelay;
use Illuminate\Console\Command;

final class MessagingRelayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messaging:relay
                            {--max-batches= : Maximum number of batches to process before exiting}
                            {--observer : Declare development observer queue before running relay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously relay unpublished outbox events to RabbitMQ';

    public function handle(OutboxRelay $relay): int
    {
        if ($this->option('observer') || (bool) config('outbox.observer_enabled', false)) {
            $this->call(MessagingTopologyObserverCommand::class);
        }

        $shouldStop = false;

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function () use (&$shouldStop): void {
                $this->info('Received SIGTERM, stopping relay gracefully after current batch...');
                $shouldStop = true;
            });

            pcntl_signal(SIGINT, function () use (&$shouldStop): void {
                $this->info('Received SIGINT, stopping relay gracefully after current batch...');
                $shouldStop = true;
            });
        }

        $maxBatchesOption = $this->option('max-batches');
        $maxBatches = $maxBatchesOption !== null
            ? (int) $maxBatchesOption
            : (config('outbox.max_batches') !== null ? (int) config('outbox.max_batches') : null);

        $batchesProcessed = 0;

        while (! $shouldStop) {
            $published = $relay->processBatch();
            $batchesProcessed++;

            if ($maxBatches !== null && $batchesProcessed >= $maxBatches) {
                break;
            }

            if ($published === 0) {
                $idleSleepMs = (int) config('outbox.idle_sleep_ms', 100);
                usleep($idleSleepMs * 1000);
            }
        }

        return self::SUCCESS;
    }
}
