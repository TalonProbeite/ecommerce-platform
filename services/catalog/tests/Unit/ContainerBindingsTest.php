<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Messaging\RabbitMqPublisher;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class ContainerBindingsTest extends TestCase
{
    public function test_repository_bindings_and_lazy_singleton_publisher(): void
    {
        $app = new Application();
        (new AppServiceProvider($app))->register();
        foreach (['Store', 'Category', 'Product', 'ProductImage', 'Stock', 'StockReservation'] as $name) {
            $interface = 'App\\Contracts\\Repositories\\' . $name . 'RepositoryInterface';
            self::assertTrue($app->bound($interface));
            self::assertInstanceOf('App\\Repositories\\' . $name . 'Repository', $app->make($interface));
        }
        self::assertFalse($app->resolved(RabbitMqPublisher::class));
        $publisher = $app->make(EventPublisherInterface::class);
        self::assertInstanceOf(RabbitMqPublisher::class, $publisher);
        self::assertSame($publisher, $app->make(EventPublisherInterface::class));
        self::assertSame($publisher, $app->make(RabbitMqPublisher::class));
    }
}
