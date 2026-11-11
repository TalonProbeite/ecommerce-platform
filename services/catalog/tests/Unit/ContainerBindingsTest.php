<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Contracts\Services\ProductImageServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\Contracts\Services\StockServiceInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\Infrastructure\Health\ReadinessChecker;
use App\Infrastructure\Storage\S3BucketGuard;
use App\Messaging\RabbitMqPublisher;
use Aws\S3\S3Client;
use ReflectionProperty;
use App\Services\CategoryService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\StoreService;
use Tests\TestCase;

class ContainerBindingsTest extends TestCase
{
    public function test_repository_bindings_and_lazy_singleton_publisher(): void
    {
        $app = $this->app;

        foreach (['Store', 'Category', 'Product', 'ProductAttribute', 'ProductImage', 'Stock', 'StockReservation'] as $name) {
            $interface = 'App\\Contracts\\Repositories\\' . $name . 'RepositoryInterface';
            self::assertTrue($app->bound($interface));
            self::assertInstanceOf('App\\Repositories\\' . $name . 'Repository', $app->make($interface));
        }
        self::assertFalse($app->resolved(RabbitMqPublisher::class));
        $publisher = $app->make(EventPublisherInterface::class);
        self::assertInstanceOf(RabbitMqPublisher::class, $publisher);
        self::assertSame($publisher, $app->make(EventPublisherInterface::class));
        self::assertSame($publisher, $app->make(RabbitMqPublisher::class));

        foreach ([
            CategoryServiceInterface::class => CategoryService::class,
            ProductImageServiceInterface::class => ProductImageService::class,
            ProductServiceInterface::class => ProductService::class,
            StockServiceInterface::class => StockService::class,
            StoreServiceInterface::class => StoreService::class,
        ] as $interface => $implementation) {
            self::assertTrue($app->bound($interface));
            self::assertInstanceOf($implementation, $app->make($interface));
        }

        self::assertTrue($app->bound(S3Client::class));
        self::assertSame($app->make(S3Client::class), $app->make(S3Client::class));

        self::assertTrue($app->bound('s3.readiness'));
        $readinessClient = $app->make('s3.readiness');
        self::assertInstanceOf(S3Client::class, $readinessClient);
        self::assertSame($readinessClient, $app->make('s3.readiness'));

        $checker = $app->make(ReadinessChecker::class);
        $checkerClient = (new ReflectionProperty($checker, 'client'))->getValue($checker);
        self::assertSame($readinessClient, $checkerClient);

        self::assertTrue($app->bound(S3BucketGuard::class));
        self::assertSame($app->make(S3BucketGuard::class), $app->make(S3BucketGuard::class));
    }
}
