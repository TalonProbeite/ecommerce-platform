<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Models\Store;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\CreateStoreData;
use App\DTO\UpdateStoreData;
use App\Models\StockReservation;

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

    public function updateStore(string $id, UpdateStoreData $data): Store
    {
        $store = Store::findOrFail($id);
        $store->fill($data->toArray());
        $store->saveOrFail();
        return $store;
    }

    public function hasActiveRentals(string $storeId): bool
    {
        return StockReservation::query()
            ->where('store_id', $storeId)
            ->whereIn('status', ['reserved', 'issued'])
            ->exists();
    }

    public function deleteStore(string $storeID): Store
    {
        $store = Store::findOrFail($storeID);
        $store->deleteOrFail();
        return $store;
    }
}
