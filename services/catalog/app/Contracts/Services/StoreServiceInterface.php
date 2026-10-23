<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTO\CreateStoreData;
use App\DTO\UpdateStoreData;
use App\Models\Store;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Mutations reject missing entities, invalid input, or invalid store ownership
 * as business-operation errors. Update DTO fields marked absent stay unchanged;
 * explicit null is accepted only for nullable schema fields.
 */
interface StoreServiceInterface
{
    /** @return LengthAwarePaginator<int, Store> */
    public function list(StoreFilters $filters): LengthAwarePaginator;

    /** Return null when absent within the requested scope. */
    public function get(string $storeId): ?Store;

    public function create(CreateStoreData $data): Store;

    public function update(string $storeId, UpdateStoreData $data): Store;

    /**
     * Reject deletion while active reserved/issued reservations or other blocking dependencies exist.
     * Enforce business checks explicitly: foreign keys do not guard soft deletion.
     */
    public function delete(string $storeId): void;
}
