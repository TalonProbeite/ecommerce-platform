<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\ProductServiceInterface;
use App\DTO\ProductFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\ProductListRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductServiceInterface $products,
    ) {
    }

    public function index(ProductListRequest $request): JsonResponse
    {
        $result = $this->products->list($request->toDTO(true));

        return ProductResource::collection($result)->response();
    }

    public function show(string $productId): JsonResponse
    {
        return (new ProductResource($this->products->getPublic($productId)))->response();
    }

    public function adminIndex(ProductListRequest $request, string $storeId): JsonResponse
    {
        $filters = $request->toDTO(false);
        $result = $this->products->list(new ProductFilters(
            storeId: $storeId,
            categoryId: $filters->categoryId,
            minPrice: $filters->minPrice,
            maxPrice: $filters->maxPrice,
            maxDeposit: $filters->maxDeposit,
            isActive: $filters->isActive,
            isAvailable: $filters->isAvailable,
            sortBy: $filters->sortBy,
            sortDirection: $filters->sortDirection,
            limit: $filters->limit,
            page: $filters->page,
            publicOnly: false,
        ));

        return ProductResource::collection($result)->response();
    }

    public function adminShow(string $storeId, string $productId): JsonResponse
    {
        return (new ProductResource($this->products->get($productId, $storeId)))->response();
    }

    public function store(CreateProductRequest $request, string $storeId): JsonResponse
    {
        return (new ProductResource($this->products->create($storeId, $request->toDTO())))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, string $storeId, string $productId): JsonResponse
    {
        return (new ProductResource($this->products->update($storeId, $productId, $request->toDTO())))->response();
    }

    public function destroy(string $storeId, string $productId): Response
    {
        $this->products->delete($storeId, $productId);

        return response()->noContent();
    }

    public function restore(string $storeId, string $productId): JsonResponse
    {
        return (new ProductResource($this->products->restore($storeId, $productId)))->response();
    }
}
