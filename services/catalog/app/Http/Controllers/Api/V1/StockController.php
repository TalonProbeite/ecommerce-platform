<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\StockServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStockRequest;
use App\Http\Resources\StockResource;
use Illuminate\Http\JsonResponse;

final class StockController extends Controller
{
    public function __construct(
        private readonly StockServiceInterface $stock,
    ) {
    }

    public function show(string $storeId, string $productId): JsonResponse
    {
        return (new StockResource($this->stock->get($storeId, $productId)))->response();
    }

    public function update(UpdateStockRequest $request, string $storeId, string $productId): JsonResponse
    {
        return (new StockResource($this->stock->setQuantity($storeId, $productId, $request->quantity())))->response();
    }
}
