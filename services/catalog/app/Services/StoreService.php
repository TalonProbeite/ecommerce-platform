<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\DTO\CreateStoreData;
use App\DTO\StoreFilters;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Exceptions\StoreNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class StoreService implements StoreServiceInterface
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository,
        private StockReservationRepositoryInterface $stockReservationRepository,
        private ProductRepositoryInterface $productRepository,
        private ProductCache $productCache,
    ) {
    }

    public function list(StoreFilters $filters): LengthAwarePaginator
    {
        return $this->storeRepository->findAll($filters);
    }

    public function get(string $storeId): Store
    {
        return $this->storeRepository->findByID($storeId)
            ?? throw new StoreNotFoundException($storeId);
    }

    public function create(CreateStoreData $data): Store
    {
        return $this->storeRepository->createStore($data);
    }

    public function update(string $storeId, UpdateStoreData $data): Store
    {
        $store = $this->get($storeId);
        $wasActive = $store->active;

        $updated = $this->storeRepository->updateStore($storeId, $data)
            ?? throw new StoreNotFoundException($storeId);

        if ($wasActive && ! $updated->active) {
            $productIds = $this->productRepository->findIdsByStore($storeId);

            DB::afterCommit(function () use ($productIds): void {
                foreach ($productIds as $productId) {
                    $this->productCache->invalidateCard($productId);
                }
                $this->productCache->invalidateLists();
            });
        }

        return $updated;
    }

    public function delete(string $storeId): void
    {
        DB::transaction(function () use ($storeId): void {
            $this->storeRepository->lockById($storeId)
                ?? throw new StoreNotFoundException($storeId);

            if ($this->stockReservationRepository->hasActiveByStore($storeId)) {
                throw new StoreHasActiveRentalsException($storeId);
            }

            $productIds = $this->productRepository->findIdsByStore($storeId);

            $this->storeRepository->deleteStore($storeId);

            DB::afterCommit(function () use ($productIds): void {
                foreach ($productIds as $productId) {
                    $this->productCache->invalidateCard($productId);
                }
                $this->productCache->invalidateLists();
            });
        });
    }
}
