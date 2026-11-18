<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\OrderActionController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderEventController;
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

    Route::get('/orders/{order}/events', [OrderEventController::class, 'index'])
        ->name('orders.events.index');

    Route::post('/orders/{order}/payment-intent', [PaymentIntentController::class, 'store'])
        ->middleware('throttle:payments-intent')
        ->name('orders.payment-intent');

    Route::post('/orders/{order}/cancel', [OrderActionController::class, 'cancel'])
        ->middleware('throttle:orders-cancel')
        ->name('orders.cancel');

    Route::post('/orders/{order}/issue', [OrderActionController::class, 'issue'])
        ->name('orders.issue');

    Route::post('/orders/{order}/return', [OrderActionController::class, 'return'])
        ->name('orders.return');

    Route::get('/analytics/overdue-rentals', [AnalyticsController::class, 'overdueRentals'])
        ->middleware('throttle:analytics-read')
        ->name('analytics.overdue-rentals');
});
