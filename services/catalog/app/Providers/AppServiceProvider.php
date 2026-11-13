<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Database\TransactionRunnerInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProcessedEventRepositoryInterface;
use App\Contracts\Repositories\ProductAttributeRepositoryInterface;
use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Contracts\Services\Inbound\OrderFulfilmentServiceInterface;
use App\Contracts\Services\Inbound\OrderStockReservationServiceInterface;
use App\Contracts\Services\ProductImageServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\Contracts\Services\StockServiceInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\Infrastructure\Cache\CategoryCache;
use App\Infrastructure\Health\ReadinessChecker;
use Aws\S3\S3Client;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\RabbitMqPublisher;
use App\Models\Category;
use App\Observers\CategoryObserver;
use App\Repositories\CategoryRepository;
use App\Repositories\ProcessedEventRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Repositories\StoreRepository;
use App\Services\CategoryService;
use App\Services\Inbound\OrderFulfilmentService;
use App\Services\Inbound\OrderStockReservationService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\StoreService;
use App\Database\DbTransactionRunner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\WorkerStopping;
use App\Infrastructure\Storage\S3BucketGuard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ProcessedEventRepositoryInterface::class, ProcessedEventRepository::class);
        $this->app->bind(ProductAttributeRepositoryInterface::class, ProductAttributeRepository::class);
        $this->app->bind(ProductImageRepositoryInterface::class, ProductImageRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(StockRepositoryInterface::class, StockRepository::class);
        $this->app->bind(StockReservationRepositoryInterface::class, StockReservationRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);

        $this->app->bind(TransactionRunnerInterface::class, DbTransactionRunner::class);

        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
        $this->app->bind(OrderFulfilmentServiceInterface::class, OrderFulfilmentService::class);
        $this->app->bind(OrderStockReservationServiceInterface::class, OrderStockReservationService::class);
        $this->app->bind(ProductImageServiceInterface::class, ProductImageService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(StockServiceInterface::class, StockService::class);
        $this->app->bind(StoreServiceInterface::class, StoreService::class);

        $this->app->singleton(RabbitMqPublisher::class);
        $this->app->alias(RabbitMqPublisher::class, EventPublisherInterface::class);
        $this->app->singleton(CategoryCache::class);
        $this->app->singleton(ProductCache::class);
        $this->app->singleton(S3Client::class, fn (): S3Client => $this->makeS3Client(5.0));
        $this->app->singleton('s3.readiness', fn (): S3Client => $this->makeS3Client(2.0));
        $this->app->when(ReadinessChecker::class)
            ->needs(S3Client::class)
            ->give(fn ($app): S3Client => $app->make('s3.readiness'));
        $this->app->singleton(S3BucketGuard::class);
    }

    private function makeS3Client(float $timeout): S3Client
    {
        $config = config('filesystems.disks.seaweedfs');

        return new S3Client([
            'version' => 'latest',
            'region' => (string) $config['region'],
            'endpoint' => (string) $config['endpoint'],
            'use_path_style_endpoint' => (bool) $config['use_path_style_endpoint'],
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
            'http' => [
                'connect_timeout' => 2.0,
                'timeout' => $timeout,
            ],
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('catalog-public', static function (Request $request): Limit {
            return Limit::perMinute(
                (int) config('catalog.rate_limits.public_read', 120)
            )->by($request->ip());
        });

        Category::observe(CategoryObserver::class);

        Event::listen(WorkerStopping::class, static function (WorkerStopping $event): void {
            if (! $event->app->resolved(RabbitMqPublisher::class)) {
                return;
            }

            $event->app->make(RabbitMqPublisher::class)->close();
        });
    }
}
