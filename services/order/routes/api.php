<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentIntentController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

Route::pattern('order', $uuid);

Route::middleware(Authenticate::class)->prefix('v1')->group(function (): void {
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('throttle:orders-create')
        ->name('orders.store');

    Route::get('/orders', [OrderController::class, 'index'])
        ->name('orders.index');

    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->name('orders.show');

    Route::post('/orders/{order}/payment-intent', [PaymentIntentController::class, 'store'])
        ->middleware('throttle:payments-intent')
        ->name('orders.payment-intent');
});
