<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Messaging\RabbitMqPublisher;
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
