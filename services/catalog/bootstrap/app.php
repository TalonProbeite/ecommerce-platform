<?php

use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\CategoryParentDescendantException;
use App\Exceptions\CategoryParentSelfException;
use App\Exceptions\HasProductsException;
use App\Exceptions\HasSubcategoriesException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidImageFileException;
use App\Exceptions\NonNullableFieldException;
use App\Exceptions\ParentCategoryNotFoundException;
use App\Exceptions\ProductCategoryNotFoundException;
use App\Exceptions\ProductHasActiveReservationsException;
use App\Exceptions\ProductImageNotFoundException;
use App\Exceptions\ProductImageStorageException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\QuantityBelowReservedException;
use App\Exceptions\ReservedUnderflowException;
use App\Exceptions\StockConcurrencyException;
use App\Exceptions\StockNotFoundException;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Exceptions\StoreNotFoundException;
use App\Exceptions\TooManyProductImagesException;
use App\Exceptions\UnsupportedImageTypeException;
use App\Http\Controllers\ReadyzController;
use App\Http\Middleware\EnsureAdminOrStoreManager;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStoreAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $exceptions->dontReport([
            CategoryNotFoundException::class,
            CategoryParentDescendantException::class,
            CategoryParentSelfException::class,
            HasProductsException::class,
            HasSubcategoriesException::class,
            InsufficientStockException::class,
            InvalidImageFileException::class,
            NonNullableFieldException::class,
            ParentCategoryNotFoundException::class,
            ProductCategoryNotFoundException::class,
            ProductHasActiveReservationsException::class,
            ProductImageNotFoundException::class,
            ProductNotFoundException::class,
            QuantityBelowReservedException::class,
            ReservedUnderflowException::class,
            StockNotFoundException::class,
            StoreHasActiveRentalsException::class,
            StoreNotFoundException::class,
            TooManyProductImagesException::class,
            UnsupportedImageTypeException::class,
        ]);

        $exceptions->dontReportWhen(
            fn (Throwable $exception): bool => $exception instanceof QueryException
                && ($exception->errorInfo[0] ?? null) === '23505',
        );

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if ($exception instanceof QueryException) {
                if (($exception->errorInfo[0] ?? null) !== '23505') {
                    return null;
                }

                return response()->json([
                    'error' => 'Slug already exists.',
                    'code' => 'slug_conflict',
                ], 409);
            }

            if (
                $exception instanceof ParentCategoryNotFoundException
                || $exception instanceof CategoryParentSelfException
                || $exception instanceof CategoryParentDescendantException
                || $exception instanceof NonNullableFieldException
            ) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors,
                ], 422);
            }

            return match (true) {
                $exception instanceof ProductImageStorageException => response()->json([
                    'error' => 'Image storage is temporarily unavailable.',
                    'code' => 'product_image_storage_unavailable',
                ], 503),
                $exception instanceof CategoryNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['category' => $exception->categoryId],
                ], 404),
                $exception instanceof ProductNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['product' => $exception->productId],
                ], 404),
                $exception instanceof ProductImageNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['product_image' => $exception->productImageId],
                ], 404),
                $exception instanceof StockNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['product' => $exception->productId],
                ], 404),
                $exception instanceof StoreNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['store' => $exception->storeId],
                ], 404),
                $exception instanceof HasProductsException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['category' => $exception->categoryId],
                ], 409),
                $exception instanceof HasSubcategoriesException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['category' => $exception->categoryId],
                ], 409),
                $exception instanceof InsufficientStockException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => [
                        'product' => $exception->productId,
                        'available' => $exception->available,
                        'requested' => $exception->requested,
                    ],
                ], 409),
                $exception instanceof ProductHasActiveReservationsException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['product' => $exception->productId],
                ], 409),
                $exception instanceof ReservedUnderflowException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => [
                        'product' => $exception->productId,
                        'reserved' => $exception->reserved,
                        'release_amount' => $exception->releaseAmount,
                    ],
                ], 409),
                $exception instanceof StockConcurrencyException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['product' => $exception->productId],
                ], 409),
                $exception instanceof StoreHasActiveRentalsException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['store' => $exception->storeID],
                ], 409),
                $exception instanceof InvalidImageFileException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['reason' => $exception->reason],
                ], 422),
                $exception instanceof ProductCategoryNotFoundException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['category' => $exception->categoryId],
                ], 422),
                $exception instanceof QuantityBelowReservedException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => [
                        'product' => $exception->productId,
                        'reserved' => $exception->reserved,
                        'requested' => $exception->requested,
                    ],
                ], 422),
                $exception instanceof TooManyProductImagesException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => [
                        'product' => $exception->productId,
                        'limit' => $exception->limit,
                    ],
                ], 422),
                $exception instanceof UnsupportedImageTypeException => response()->json([
                    'error' => $exception->getMessage(),
                    'details' => ['mime_type' => $exception->mimeType],
                ], 422),
                default => null,
            };
        });
    })->create();
