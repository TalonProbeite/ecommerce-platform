<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\Webhooks\HandleStripeEvent;
use App\Application\Payments\Webhooks\OnPaymentFailed;
use App\Application\Payments\Webhooks\OnPaymentSucceeded;
use App\Application\Ports\Clock;
use App\Application\Ports\IdempotencyLock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\OrderReadRepository;
use App\Application\Ports\OrderRepository;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\ProcessedEventStore;
use App\Application\Ports\ProductSnapshotRepository;
use App\Application\Ports\StripeEventStore;
use App\Application\Ports\TransactionRunnerInterface;
use App\Application\Ports\WebhookSignatureVerifier;
use App\Http\Auth\AuthUser;
use App\Infrastructure\Idempotency\RedisIdempotencyLock;
use App\Infrastructure\Messaging\Inbound\Handlers\ProductCreatedHandler;
use App\Infrastructure\Messaging\Inbound\Handlers\ProductDeletedHandler;
use App\Infrastructure\Messaging\Inbound\Handlers\ProductUpdatedHandler;
use App\Infrastructure\Messaging\Inbound\Handlers\StockReservationFailedHandler;
use App\Infrastructure\Messaging\Inbound\Handlers\StockReservedHandler;
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
use App\Infrastructure\Payments\Stripe\StripePaymentGateway;
use App\Infrastructure\Payments\Stripe\StripeWebhookSignatureVerifier;
use App\Infrastructure\Persistence\EloquentOrderReadRepository;
use App\Infrastructure\Persistence\EloquentOrderRepository;
use App\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Infrastructure\Persistence\EloquentProcessedEventStore;
use App\Infrastructure\Persistence\EloquentProductSnapshotRepository;
use App\Infrastructure\Persistence\EloquentStripeEventStore;
use App\Infrastructure\Persistence\SystemClock;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use App\Infrastructure\Persistence\UuidV7IdGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\WorkerStopping;
use Stripe\StripeClient;

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
        $this->app->bind(OrderReadRepository::class, EloquentOrderReadRepository::class);
        $this->app->bind(ProductSnapshotRepository::class, EloquentProductSnapshotRepository::class);
        $this->app->bind(ProcessedEventStore::class, EloquentProcessedEventStore::class);
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
        $this->app->bind(StripeEventStore::class, EloquentStripeEventStore::class);

        $this->app->singleton(StripeClient::class, static function (): StripeClient {
            $secret = config('stripe.secret');

            if (! is_string($secret) || $secret === '') {
                throw new PaymentProviderUnavailable('Stripe secret key is not configured.');
            }

            $config = ['api_key' => $secret];
            $apiVersion = config('stripe.api_version');

            if (is_string($apiVersion) && $apiVersion !== '') {
                $config['stripe_version'] = $apiVersion;
            }

            return new StripeClient($config);
        });
        $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
        $this->app->bind(WebhookSignatureVerifier::class, StripeWebhookSignatureVerifier::class);
        $this->app->singleton(OnPaymentSucceeded::class);
        $this->app->singleton(OnPaymentFailed::class);
        $this->app->singleton(HandleStripeEvent::class);

        $this->app->bind(IdempotencyLock::class, static function ($app): IdempotencyLock {
            return new RedisIdempotencyLock($app->make('redis')->connection());
        });

        // Outbox Messaging
        $this->app->bind(OutboxRepositoryInterface::class, OutboxRepository::class);
        $this->app->bind(OutboxWriterInterface::class, OutboxWriter::class);

        $this->app->singleton(AmqpOutboxPublisher::class);
        $this->app->alias(AmqpOutboxPublisher::class, OutboxMessagePublisherInterface::class);

        $this->app->singleton(Topology::class);
        $this->app->singleton(OutboxRelay::class);

        // Inbound Messaging
        $this->app->tag([
            ProductCreatedHandler::class,
            ProductUpdatedHandler::class,
            ProductDeletedHandler::class,
            StockReservedHandler::class,
            StockReservationFailedHandler::class,
        ], 'inbound.handlers');
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

        RateLimiter::for('orders-create', static function (Request $request): Limit {
            $user = $request->attributes->get(AuthUser::ATTRIBUTE);

            return Limit::perMinute(30)->by(
                $user instanceof AuthUser ? 'user:' . $user->id : (string) $request->ip(),
            );
        });

        RateLimiter::for('payments-intent', static function (Request $request): Limit {
            $user = $request->attributes->get(AuthUser::ATTRIBUTE);

            return Limit::perMinute(20)->by(
                $user instanceof AuthUser ? 'user:' . $user->id : (string) $request->ip(),
            );
        });

        RateLimiter::for('orders-cancel', static function (Request $request): Limit {
            $user = $request->attributes->get(AuthUser::ATTRIBUTE);

            return Limit::perMinute(30)->by(
                $user instanceof AuthUser ? 'user:' . $user->id : (string) $request->ip(),
            );
        });

        Event::listen(WorkerStopping::class, static function (WorkerStopping $event): void {
            if ($event->app->resolved(AmqpOutboxPublisher::class)) {
                $event->app->make(AmqpOutboxPublisher::class)->close();
            }
        });
    }
}
