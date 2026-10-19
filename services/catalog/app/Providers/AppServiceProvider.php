<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Messaging\RabbitMqPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            EventPublisherInterface::class,
            RabbitMqPublisher::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
