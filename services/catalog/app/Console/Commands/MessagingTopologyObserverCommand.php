<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Messaging\ObserverTopologyInterface;
use Illuminate\Console\Command;
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

    public function handle(ObserverTopologyInterface $topology): int
    {
        $queueName = $topology->queueName();
        $exchangeName = $topology->exchangeName();

        $this->info(sprintf('Declaring observer queue "%s" on exchange "%s"...', $queueName, $exchangeName));

        try {
            $topology->declare();

            $this->info(sprintf('Observer queue "%s" bound to "%s" with routing key "#".', $queueName, $exchangeName));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to declare observer topology: %s', $e->getMessage()));

            return self::FAILURE;
        }
    }
}
