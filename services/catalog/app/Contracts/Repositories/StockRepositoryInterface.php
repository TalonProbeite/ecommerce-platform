<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Stock;
use App\DTO\UpdateStockData;

interface StockRepositoryInterface
{
    public function findByProductId(string $productId): ?Stock;

    public function findById(string $stockId): ?Stock;

    public function createStock(string $productId, string $storeId, int $quantity): Stock;

    public function applyDelta(Stock $stock, UpdateStockData $data): Stock;
}
