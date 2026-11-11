<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreAccess;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Tests\TestCase;

class MiddlewareAliasTest extends TestCase
{
    public function test_catalog_middleware_aliases_are_registered(): void
    {
        $this->app->make(Kernel::class);

        $router = $this->app->make('router');

        self::assertInstanceOf(Router::class, $router);

        $middleware = $router->getMiddleware();

        self::assertSame(EnsureRole::class, $middleware['catalog.role']);
        self::assertSame(EnsureStoreAccess::class, $middleware['catalog.store']);
    }
}
