<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Models\Store;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreRepository implements StoreRepositoryInterface
{
    /**
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

    public function findByID(string $id): ?Store
    {
        return Store::find($id);
    }

    public function save(Store $store): Store
    {
        $store->saveOrFail();
        return $store;
    }

    public function delete(Store $store): void
    {
        $store->deleteOrFail();
    }
}
