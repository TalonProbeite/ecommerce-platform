<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Models\Store;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\CreateStoreData;
use App\DTO\UpdateStoreData;

class StoreRepository implements StoreRepositoryInterface
{
    /**
     * Find paginated stores matching the given filters.
     *
     * @return LengthAwarePaginator<int, Store>
     */
    public function findAll(StoreFilters $filters): LengthAwarePaginator
    {
        return Store::query()
            ->when(
                $filters->active !== null,
                fn ($q) => $q->where('active', $filters->active)
            )
            ->orderBy('created_at', 'desc')
            ->orderBy('id')
            ->paginate(
                perPage: $filters->limit,
                page: $filters->page,
            );
    }

    /**
     * Find a store by its ID.
     */
    public function findByID(string $id): ?Store
    {
        return Store::find($id);
    }

    public function createStore(CreateStoreData $data): Store
    {
        $store =  new Store();
        $store->fill($data->toArray());
        $store->saveOrFail();
        return $store;
    }

    public function updateStore(string $id, UpdateStoreData $data): ?Store
    {
        $store = Store::find($id);

        if ($store === null) {
            return null;
        }

        $store->fill($data->toArray());
        $store->saveOrFail();

        return $store;
    }

    public function deleteStore(string $storeID): ?Store
    {
        $store = Store::find($storeID);

        if ($store === null) {
            return null;
        }

        $store->deleteOrFail();

        return $store;
    }

    /**
     * Lock a store row for update. Must be called inside a DB transaction.
     */
    public function lockById(string $storeId): ?Store
    {
        return Store::query()
            ->whereKey($storeId)
            ->lockForUpdate()
            ->first();
    }
}
