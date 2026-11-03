<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductAttributeRepositoryInterface;
use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Contracts\Services\ProductImageServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\Contracts\Services\StockServiceInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\Messaging\RabbitMqPublisher;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Repositories\StoreRepository;
use App\Services\CategoryService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\StoreService;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(ProductAttributeRepositoryInterface::class, ProductAttributeRepository::class);
        $this->app->bind(ProductImageRepositoryInterface::class, ProductImageRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(StockRepositoryInterface::class, StockRepository::class);
        $this->app->bind(StockReservationRepositoryInterface::class, StockReservationRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);

        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
        $this->app->bind(ProductImageServiceInterface::class, ProductImageService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(StockServiceInterface::class, StockService::class);
        $this->app->bind(StoreServiceInterface::class, StoreService::class);

        $this->app->singleton(RabbitMqPublisher::class);
        $this->app->alias(RabbitMqPublisher::class, EventPublisherInterface::class);
        $this->app->singleton(S3BucketGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(WorkerStopping::class, static function (WorkerStopping $event): void {
            if (! $event->app->resolved(RabbitMqPublisher::class)) {
                return;
            }

            $event->app->make(RabbitMqPublisher::class)->close();
        });
    }
}
