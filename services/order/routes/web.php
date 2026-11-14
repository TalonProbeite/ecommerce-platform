<?php

use App\Http\Controllers\ReadyzController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', static fn () => response('OK', 200)->header('Content-Type', 'text/plain'));
Route::get('/readyz', ReadyzController::class);
