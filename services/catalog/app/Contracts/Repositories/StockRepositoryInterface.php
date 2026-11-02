<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Stock;

interface StockRepositoryInterface
{
    /**
     * Find stock by product, scoped to the store.
     */
    public function findByProductId(string $productId, string $storeId): ?Stock;

    /**
     * Lock the stock row for the rest of the current transaction.
     * Must be called inside a DB transaction.
     */
    public function lockByProductId(string $productId, string $storeId): ?Stock;

    public function createStock(string $productId, string $storeId, int $quantity): Stock;

    /**
     * Conditional update: sets quantity only if reserved <= $quantity.
     *
     * @return int Number of affected rows (0 or 1).
     */
    public function setQuantityIfNotBelowReserved(string $stockId, int $quantity): int;

    /**
     * Conditional update: adds $delta to reserved only if the result
     * stays within [0, quantity].
     *
     * @return int Number of affected rows (0 or 1).
     */
    public function adjustReserved(string $stockId, int $delta): int;
}
