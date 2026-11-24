<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Outbox\OutboxWriterInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Services\StockServiceInterface;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\QuantityBelowReservedException;
use App\Exceptions\ReservedUnderflowException;
use App\Exceptions\StockConcurrencyException;
use App\Exceptions\StockNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\StockChanged;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class StockService implements StockServiceInterface
{
    public function __construct(
        private StockRepositoryInterface $stockRepository,
        private OutboxWriterInterface $outbox,
        private readonly ProductCache $productCache,
    ) {
    }

    public function get(string $storeId, string $productId): Stock
    {
        return $this->stockRepository->findByProductId($productId, $storeId)
            ?? throw new StockNotFoundException($productId);
    }

    public function setQuantity(string $storeId, string $productId, int $quantity): Stock
    {
        return DB::transaction(function () use ($storeId, $productId, $quantity): Stock {
            $stock = $this->lockOrFail($storeId, $productId);

            if ($quantity < $stock->reserved) {
                throw new QuantityBelowReservedException($productId, $stock->reserved, $quantity);
            }

            // Row is locked, so 0 affected rows means a bug, not a race.
            if ($this->stockRepository->setQuantityIfNotBelowReserved($stock->id, $quantity) !== 1) {
                throw new StockConcurrencyException($productId);
            }

            return $this->refreshAndPublish($stock);
        });
    }

    public function reserveUnits(string $storeId, string $productId, int $quantity): Stock
    {
        $this->assertPositive($quantity);

        return $this->changeReserved($storeId, $productId, $quantity);
    }

    public function releaseUnits(string $storeId, string $productId, int $quantity): Stock
    {
        $this->assertPositive($quantity);

        return $this->changeReserved($storeId, $productId, -$quantity);
    }

    private function changeReserved(string $storeId, string $productId, int $delta): Stock
    {
        return DB::transaction(function () use ($storeId, $productId, $delta): Stock {
            $stock = $this->lockOrFail($storeId, $productId);

            $newReserved = $stock->reserved + $delta;

            if ($newReserved < 0) {
                throw new ReservedUnderflowException($productId, $stock->reserved, -$delta);
            }

            if ($newReserved > $stock->quantity) {
                throw new InsufficientStockException($productId, $stock->quantity - $stock->reserved, $delta);
            }

            if ($this->stockRepository->adjustReserved($stock->id, $delta) !== 1) {
                throw new StockConcurrencyException($productId);
            }

            return $this->refreshAndPublish($stock);
        });
    }

    private function lockOrFail(string $storeId, string $productId): Stock
    {
        return $this->stockRepository->lockByProductId($productId, $storeId)
            ?? throw new StockNotFoundException($productId);
    }

    private function refreshAndPublish(Stock $stock): Stock
    {
        $updated = $stock->refresh();

        $this->outbox->enqueue(
            StockChanged::fromModel($updated),
            $this->modelTime($updated->updated_at),
        );

        DB::afterCommit(function () use ($updated): void {
            $this->productCache->invalidateCard($updated->product_id);
        });

        return $updated;
    }

    private function modelTime(?DateTimeInterface $time): CarbonImmutable
    {
        return $time !== null ? CarbonImmutable::instance($time) : CarbonImmutable::now();
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }
    }
}
