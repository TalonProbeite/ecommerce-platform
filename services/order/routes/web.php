<?php

use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->name('webhooks.stripe');
