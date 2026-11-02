<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockRepositoryInterface;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

final class StockRepository implements StockRepositoryInterface
{
    public function findByProductId(string $productId, string $storeId): ?Stock
    {
        return Stock::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->first();
    }

    public function lockByProductId(string $productId, string $storeId): ?Stock
    {
        return Stock::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->lockForUpdate()
            ->first();
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

    public function setQuantityIfNotBelowReserved(string $stockId, int $quantity): int
    {
        return Stock::query()
            ->whereKey($stockId)
            ->where('reserved', '<=', $quantity)
            ->update([
                'quantity' => $quantity,
                'updated_at' => now(),
            ]);
    }

    public function adjustReserved(string $stockId, int $delta): int
    {
        return Stock::query()
            ->whereKey($stockId)
            ->whereRaw('reserved + ? BETWEEN 0 AND quantity', [$delta])
            ->update([
                'reserved' => DB::raw('reserved + ' . $delta),
                'updated_at' => now(),
            ]);
    }
}