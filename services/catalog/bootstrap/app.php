<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureAdminOrStoreManager;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreAccess;
use App\Http\Controllers\ReadyzController;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/healthz',
        then: function () {
            Route::get('/readyz', ReadyzController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'store.management' => EnsureAdminOrStoreManager::class,
            'catalog.role' => EnsureRole::class,
            'catalog.store' => EnsureStoreAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (QueryException $exception): ?JsonResponse {
            if (($exception->errorInfo[0] ?? null) !== '23505') {
                return null;
            }

            return response()->json([
                'error' => 'Slug already exists.',
                'code' => 'slug_conflict',
            ], 409);
        });
    })->create();
