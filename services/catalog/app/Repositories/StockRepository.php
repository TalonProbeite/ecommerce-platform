<?php

declare(strict_types=1);


namespace App\Repositories;

use App\Contracts\Repositories\StockRepositoryInterface;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

class StockRepository implements StockRepositoryInterface
{
    public function findByProductAndStore(string $productID, string $storeID): ?Stock
    {
        return Stock::where('product_id', $productID)
            ->where('store_id', $storeID)
            ->get();
    }

    public function findByStore(string $storeID): Collection
    {
        return Stock::where('store_id', $storeID)
            ->get();
    }

    public function save(Stock $Stock): Stock
    {
        $Stock->saveOrFail;
        return $Stock;
    }

    public function delete(Stock $Stock): void
    {
        $Stock->deleteOrFail();
    }
}
