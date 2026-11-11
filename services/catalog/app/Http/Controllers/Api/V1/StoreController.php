<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\StoreServiceInterface;
use App\DTO\CreateStoreData;
use App\DTO\StoreFilters;
use App\DTO\UpdateStoreData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStoreRequest;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class StoreController extends Controller
{
    public function __construct(
        private readonly StoreServiceInterface $stores,
    ) {
    }

    public function index(StoreListRequest $request): JsonResponse
    {
        $stores = $this->stores->list(StoreFilters::fromArray($request->validated()));

        return StoreResource::collection($stores)->response();
    }

    public function show(string $storeId): JsonResponse
    {
        return (new StoreResource($this->stores->get($storeId)))->response();
    }

    public function store(CreateStoreRequest $request): JsonResponse
    {
        return (new StoreResource($this->stores->create(CreateStoreData::fromArray($request->validated()))))->response()->setStatusCode(201);
    }

    public function update(UpdateStoreRequest $request, string $storeId): JsonResponse
    {
        return (new StoreResource($this->stores->update($storeId, UpdateStoreData::fromArray($request->validated()))))->response();
    }

    public function destroy(string $storeId): Response
    {
        $this->stores->delete($storeId);

        return response()->noContent();
    }
}
