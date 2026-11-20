<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Contracts\Repositories\StockRepositoryInterface;
use App\Models\Stock;
use Ramsey\Uuid\Uuid;

final class FakeStockRepository implements StockRepositoryInterface
{
    /**
     * @var array<string, Stock>
     */
    private array $stocks = [];

    public function seed(string $productId, string $storeId, int $quantity, int $reserved = 0): Stock
    {
        $stock = new Stock();
        $stock->forceFill([
            'id' => $this->key($productId, $storeId),
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'reserved' => $reserved,
        ]);

        $this->stocks[$this->key($productId, $storeId)] = $stock;

        return $stock;
    }

    public function get(string $productId, string $storeId): ?Stock
    {
        return $this->stocks[$this->key($productId, $storeId)] ?? null;
    }

    public function findByProductId(string $productId, string $storeId): ?Stock
    {
        return $this->get($productId, $storeId);
    }

    public function lockByProductId(string $productId, string $storeId): ?Stock
    {
        return $this->get($productId, $storeId);
    }

    public function createStock(string $productId, string $storeId, int $quantity): Stock
    {
        return $this->seed($productId, $storeId, $quantity);
    }

    public function setQuantityIfNotBelowReserved(string $stockId, int $quantity): int
    {
        $stock = $this->findByKey($stockId);

        if ($stock === null || $stock->reserved > $quantity) {
            return 0;
        }

        $stock->quantity = $quantity;

        return 1;
    }

    public function adjustReserved(string $stockId, int $delta): int
    {
        $stock = $this->findByKey($stockId);

        if ($stock === null) {
            return 0;
        }

        $newReserved = $stock->reserved + $delta;

        if ($newReserved < 0 || $newReserved > $stock->quantity) {
            return 0;
        }

        $stock->reserved = $newReserved;

        return 1;
    }

    private function findByKey(string $key): ?Stock
    {
        return $this->stocks[$key] ?? null;
    }

    private function key(string $productId, string $storeId): string
    {
        return $productId . '@' . $storeId;
    }

    public static function uuid(): string
    {
        return (string) Uuid::uuid7();
    }
}
