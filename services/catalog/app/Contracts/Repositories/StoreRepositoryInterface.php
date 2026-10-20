<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\DTO\StoreFilters;

interface StoreRepositoryInterface
{
    public function findByID(string $id): ?Store;
    /**
     * @return LengthAwarePaginator<int, Store>
     */
    public function findAll(StoreFilters $filters): LengthAwarePaginator;
    public function save(Store $store): Store;
    public function delete(Store $store): void;
}
