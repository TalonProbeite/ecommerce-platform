<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\CategoryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryServiceInterface $categories,
    ) {
    }

    public function index(string $storeId): JsonResponse
    {
        return CategoryResource::collection($this->categories->list($storeId))->response();
    }

    public function show(string $storeId, string $categoryId): JsonResponse
    {
        $category = $this->categories->getWithAncestors($categoryId, $storeId);

        return (new CategoryResource($category))->response();
    }

    public function children(string $storeId, string $categoryId): JsonResponse
    {
        return CategoryResource::collection($this->categories->getChildren($categoryId, $storeId))->response();
    }

    public function store(CreateCategoryRequest $request, string $storeId): JsonResponse
    {
        return (new CategoryResource($this->categories->create($storeId, $request->toDTO())))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, string $storeId, string $categoryId): JsonResponse
    {
        return (new CategoryResource($this->categories->update($categoryId, $storeId, $request->toDTO())))->response();
    }

    public function destroy(string $storeId, string $categoryId): Response
    {
        $this->categories->delete($categoryId, $storeId);

        return response()->noContent();
    }
}
