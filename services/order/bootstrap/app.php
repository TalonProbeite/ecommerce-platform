<?php

use App\Http\Controllers\ReadyzController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\VerifyRequestOrigin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/healthz',
        then: function (): void {
            Route::get('/readyz', ReadyzController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reject cross-site unsafe requests before authentication runs.
        $middleware->append(VerifyRequestOrigin::class);

        // Authenticate must populate AuthUser before the orders-create limiter runs.
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            Authenticate::class,
        );

        $middleware->validateCsrfTokens(except: ['webhooks/stripe']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
