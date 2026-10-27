<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\StoreFilters;
use App\DTO\CreateStoreData;
use App\DTO\UpdateStoreData;

interface StoreRepositoryInterface
{
    /**
     * Find a store by its ID.
     */
    public function findByID(string $id): ?Store;

    /**
     * Find paginated stores matching the given filters.
     *
     * @return LengthAwarePaginator<int, Store>
     */
    public function findAll(StoreFilters $filters): LengthAwarePaginator;

    public function createStore(CreateStoreData $data): Store;

    public function updateStore(string $id, UpdateStoreData $data): Store;

    public function deleteStore(string $storeID): Store;
}
