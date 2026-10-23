<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Messaging\RabbitMqPublisher;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Repositories\StoreRepository;
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
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ProductImageRepositoryInterface::class, ProductImageRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(StockRepositoryInterface::class, StockRepository::class);
        $this->app->bind(StockReservationRepositoryInterface::class, StockReservationRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);

        $this->app->singleton(RabbitMqPublisher::class);
        $this->app->alias(RabbitMqPublisher::class, EventPublisherInterface::class);
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
