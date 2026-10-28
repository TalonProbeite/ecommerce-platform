<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\Services\StoreServiceInterface;
use App\DTO\CreateStoreData;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

final class StoreServices implements StoreServiceInterface
{
    public function __construct(
        private StoreRepositoryInterface $storeRepo,
    ) {
    }


    public function list(StoreFilters $filters): LengthAwarePaginator
    {
        return $this->storeRepo->findAll($filters);
    }

    public function get(string $storeID): ?Store
    {
        return $this->storeRepo->findByID($storeID);
    }


    public function create(CreateStoreData $data): Store
    {
        return $this->storeRepo->createStore($data);
    }

    public function update(string $storeID, UpdateStoreData $data): Store
    {
        if ($data->nameProvided && $data->name === null) {
            throw ValidationException::withMessages([
                'name' => 'The name field cannot be null.',
            ]);
        }

        if ($data->activeProvided && $data->active === null) {
            throw ValidationException::withMessages([
                'active' => 'The active field cannot be null.',
            ]);
        }

        return $this->storeRepo->updateStore($storeID, $data);
    }


    public function delete(string $storeId): void
    {
        if ($this->storeRepo->hasActiveRentals($storeId)) {
            throw new StoreHasActiveRentalsException($storeId);
        }

        $this->storeRepo->deleteStore($storeId);
    }
}
