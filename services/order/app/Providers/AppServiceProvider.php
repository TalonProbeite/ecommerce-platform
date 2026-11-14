<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\TransactionRunnerInterface as PortTransactionRunnerInterface;
use App\Contracts\Database\TransactionRunnerInterface;
use App\Infrastructure\Messaging\Inbound\InboundHandlerRegistry;
use App\Infrastructure\Messaging\Inbound\InboundMessageRouter;
use App\Infrastructure\Messaging\Outbox\AmqpOutboxPublisher;
use App\Infrastructure\Messaging\Outbox\OutboxMessagePublisherInterface;
use App\Infrastructure\Messaging\Outbox\OutboxRelay;
use App\Infrastructure\Messaging\Outbox\OutboxRepository;
use App\Infrastructure\Messaging\Outbox\OutboxRepositoryInterface;
use App\Infrastructure\Messaging\Outbox\OutboxWriter;
use App\Infrastructure\Messaging\Outbox\OutboxWriterInterface;
use App\Infrastructure\Messaging\Topology;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\WorkerStopping;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Database Transactions
        $this->app->bind(PortTransactionRunnerInterface::class, DbTransactionRunner::class);
        $this->app->bind(TransactionRunnerInterface::class, DbTransactionRunner::class);

        // Outbox Messaging
        $this->app->bind(OutboxRepositoryInterface::class, OutboxRepository::class);
        $this->app->bind(OutboxWriterInterface::class, OutboxWriter::class);

        $this->app->singleton(AmqpOutboxPublisher::class);
        $this->app->alias(AmqpOutboxPublisher::class, OutboxMessagePublisherInterface::class);

        $this->app->singleton(Topology::class);
        $this->app->singleton(OutboxRelay::class);

        // Inbound Messaging (Registration of inbound handlers is left empty: router must reject anything without handlers)
        $this->app->tag([], 'inbound.handlers');
        $this->app->singleton(InboundHandlerRegistry::class, static function ($app): InboundHandlerRegistry {
            return new InboundHandlerRegistry($app->tagged('inbound.handlers'));
        });
        $this->app->singleton(InboundMessageRouter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Event::listen(WorkerStopping::class, static function (WorkerStopping $event): void {
            if ($event->app->resolved(AmqpOutboxPublisher::class)) {
                $event->app->make(AmqpOutboxPublisher::class)->close();
            }
        });
    }
}
