<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Group A: public read, no JWT.
    Route::middleware(['throttle:catalog-public'])->group(function () {
        Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
        Route::get('/stores/{storeId}', [StoreController::class, 'show'])->name('stores.show');

        Route::get('/stores/{storeId}/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/stores/{storeId}/categories/{categoryId}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('/stores/{storeId}/categories/{categoryId}/children', [CategoryController::class, 'children'])->name('categories.children');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{productId}', [ProductController::class, 'show'])->name('products.show');

        Route::get('/products/{productId}/images', [ProductImageController::class, 'index'])->name('products.images.index');
        Route::get('/products/{productId}/images/{imageId}', [ProductImageController::class, 'show'])->name('products.images.show');
    });

    // Group B: admin area, JWT and role required.
    Route::prefix('admin')
        ->middleware(['store.management', 'catalog.role:admin,store_manager', 'throttle:catalog-public'])
        ->group(function () {

            // Admin only, no storeId.
            Route::post('/stores', [StoreController::class, 'store'])
                ->middleware('catalog.role:admin')
                ->name('admin.stores.store');

            // Routes with storeId.
            Route::prefix('stores/{storeId}')
                ->middleware('catalog.store')
                ->group(function () {
                    Route::patch('/', [StoreController::class, 'update'])->name('admin.stores.update');
                    Route::delete('/', [StoreController::class, 'destroy'])
                        ->middleware('catalog.role:admin')
                        ->name('admin.stores.destroy');

                    Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
                    Route::patch('/categories/{categoryId}', [CategoryController::class, 'update'])->name('admin.categories.update');
                    Route::delete('/categories/{categoryId}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');

                    Route::get('/products', [ProductController::class, 'adminIndex'])->name('admin.products.index');
                    Route::get('/products/{productId}', [ProductController::class, 'adminShow'])->name('admin.products.show');
                    Route::post('/products', [ProductController::class, 'store'])->name('admin.products.store');
                    Route::patch('/products/{productId}', [ProductController::class, 'update'])->name('admin.products.update');
                    Route::delete('/products/{productId}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
                    Route::post('/products/{productId}/restore', [ProductController::class, 'restore'])->name('admin.products.restore');

                    Route::post('/products/{productId}/images', [ProductImageController::class, 'store'])->name('admin.products.images.store');
                    Route::patch('/products/{productId}/images/{imageId}/main', [ProductImageController::class, 'setMain'])->name('admin.products.images.set_main');
                    Route::delete('/products/{productId}/images/{imageId}', [ProductImageController::class, 'destroy'])->name('admin.products.images.destroy');

                    Route::get('/products/{productId}/stock', [StockController::class, 'show'])->name('admin.stock.show');
                    Route::patch('/products/{productId}/stock', [StockController::class, 'update'])->name('admin.stock.update');
                });
        });
});
