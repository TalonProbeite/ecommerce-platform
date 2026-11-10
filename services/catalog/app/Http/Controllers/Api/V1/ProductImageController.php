<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\ProductImageServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProductImageRequest;
use App\Http\Resources\ProductImageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductImageServiceInterface $images,
    ) {
    }

    public function index(string $productId): JsonResponse
    {
        return ProductImageResource::collection($this->images->listPublic($productId))->response();
    }

    public function show(string $productId, string $imageId): JsonResponse
    {
        return (new ProductImageResource($this->images->getPublic($productId, $imageId)))->response();
    }

    public function store(UploadProductImageRequest $request, string $storeId, string $productId): JsonResponse
    {
        $file = $request->file('image');

        return (new ProductImageResource($this->images->upload($storeId, $productId, $file)))->response()->setStatusCode(201);
    }

    public function setMain(string $storeId, string $productId, string $imageId): JsonResponse
    {
        return (new ProductImageResource($this->images->setMain($storeId, $productId, $imageId)))->response();
    }

    public function destroy(string $storeId, string $productId, string $imageId): Response
    {
        $this->images->delete($storeId, $productId, $imageId);

        return response()->noContent();
    }
}
