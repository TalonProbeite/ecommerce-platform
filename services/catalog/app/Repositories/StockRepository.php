<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockRepositoryInterface;
use App\Models\Stock;
use App\DTO\UpdateStockData;

class StockRepository implements StockRepositoryInterface
{
    public function findByProductId(string $productId): ?Stock
    {
        return Stock::where('product_id', $productId)
            ->first();
    }

    public function lockByProductId(string $productId, ?string $storeId = null): ?Stock
    {
        return Stock::query()
            ->where('product_id', $productId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->lockForUpdate()
            ->first();
    }

    public function findById(string $stockId): ?Stock
    {
        return Stock::find($stockId);
    }

    public function createStock(string $productId, string $storeId, int $quantity): Stock
    {
        return Stock::create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'reserved' => 0,
        ]);
    }

    public function applyDelta(Stock $stock, UpdateStockData $data): Stock
    {
        if ($data->quantityDelta !== null) {
            $stock->quantity += $data->quantityDelta;
        }

        if ($data->reservedDelta !== null) {
            $stock->reserved += $data->reservedDelta;
        }

        $stock->saveOrFail();

        return $stock;
    }
}
