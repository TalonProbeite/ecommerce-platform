<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\DTO\CreateStoreData;
use App\DTO\StoreFilters;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Exceptions\StoreNotFoundException;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StoreService implements StoreServiceInterface
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository,
        private StockReservationRepositoryInterface $stockReservationRepository,
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
        return $this->storeRepository->updateStore($storeId, $data);
    }

    public function delete(string $storeId): void
    {
        if ($this->stockReservationRepository->hasActiveByStore($storeId)) {
            throw new StoreHasActiveRentalsException($storeId);
        }

        $this->storeRepository->deleteStore($storeId);
    }
}
