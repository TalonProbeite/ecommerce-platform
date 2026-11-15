<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\TransactionRunnerInterface;
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
use App\Infrastructure\Persistence\EloquentOrderRepository;
use App\Infrastructure\Persistence\EloquentProductSnapshotRepository;
use App\Infrastructure\Persistence\SystemClock;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use App\Infrastructure\Persistence\UuidV7IdGenerator;
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
        $this->app->bind(TransactionRunnerInterface::class, DbTransactionRunner::class);

        // Persistence and time
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidV7IdGenerator::class);
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
        $this->app->bind(ProductSnapshotRepository::class, EloquentProductSnapshotRepository::class);

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
