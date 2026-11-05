<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog API (v1)
|--------------------------------------------------------------------------
|
| The groups below are intentionally empty. Controllers, route actions,
| Form Requests, API Resources, and service calls will be added in a
| later change.
|
| The JWT authentication middleware is registered as "store.management".
| It stores the decoded payload in $request->attributes under "jwt_data".
| The role and store_id values are read from that payload.
|
| Route parameter names are fixed:
|   {storeId}, {categoryId}, {productId}, {imageId}
| Never use {store}, {id}, or {store_id} as route parameter names.
|
| Rule: every route that contains {storeId} and uses a non-GET method
| must also use the catalog.store middleware.
|
*/

Route::prefix('v1')->group(function () {
    // Group A: public read, no JWT, throttle only (routes added with controllers).
    Route::middleware(['throttle:catalog-public'])->group(function () {
        // Intentionally empty.
    });

    // Group B: admin area, JWT and role required.
    Route::prefix('admin')
        ->middleware(['store.management', 'catalog.role:admin,store_manager', 'throttle:catalog-public'])
        ->group(function () {
            // Intentionally empty.
        });
});

/*
|--------------------------------------------------------------------------
| Planned route schema
|--------------------------------------------------------------------------
|
| Group A - public read (no JWT, throttle:catalog-public)
|
|   GET /stores
|   GET /stores/{storeId}
|   GET /stores/{storeId}/categories
|   GET /stores/{storeId}/categories/{categoryId}
|   GET /stores/{storeId}/categories/{categoryId}/children
|   GET /products
|   GET /products/{productId}
|   GET /products/{productId}/images
|   GET /products/{productId}/images/{imageId}
|
| Group B - admin area (store.management + catalog.role:admin,store_manager
| + throttle:catalog-public)
|
|   Admin only, no storeId:
|
|   POST /stores
|       Route name: admin.stores.store
|       Extra middleware: catalog.role:admin
|
|   Nested under /stores/{storeId} with catalog.store middleware:
|
|   PATCH /stores/{storeId}
|   DELETE /stores/{storeId}
|       Extra middleware: catalog.role:admin
|
|   Categories:
|
|   POST /stores/{storeId}/categories
|   PATCH /stores/{storeId}/categories/{categoryId}
|   DELETE /stores/{storeId}/categories/{categoryId}
|
|   Products:
|
|   GET /stores/{storeId}/products
|   GET /stores/{storeId}/products/{productId}
|   POST /stores/{storeId}/products
|   PATCH /stores/{storeId}/products/{productId}
|   DELETE /stores/{storeId}/products/{productId}
|   POST /stores/{storeId}/products/{productId}/restore
|
|   Product images:
|
|   POST /stores/{storeId}/products/{productId}/images
|   PATCH /stores/{storeId}/products/{productId}/images/{imageId}/main
|       Action: setMain
|   DELETE /stores/{storeId}/products/{productId}/images/{imageId}
|
|   Stock:
|
|   GET /stores/{storeId}/products/{productId}/stock
|   PATCH /stores/{storeId}/products/{productId}/stock
|
*/
