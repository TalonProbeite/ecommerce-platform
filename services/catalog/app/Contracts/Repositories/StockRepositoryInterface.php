<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

interface StockRepositoryInterface
{
    /**
     * Find stock by product ID and store ID.
     */
    public function findByProductAndStore(string $productID, string $storeID): ?Stock;

    /**
     * Find all stock records for a given store.
     *
     * @return Collection<int, Stock>
     */
    public function findByStore(string $storeID): Collection;

    /**
     * Save a stock record.
     */
    public function save(Stock $stock): Stock;

    /**
     * Delete a stock record.
     */
    public function delete(Stock $stock): void;
}
