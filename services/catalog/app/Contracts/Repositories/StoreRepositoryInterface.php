<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\StoreFilters;

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

    /**
     * Save a store.
     */
    public function save(Store $store): Store;

    /**
     * Delete a store.
     */
    public function delete(Store $store): void;
}
