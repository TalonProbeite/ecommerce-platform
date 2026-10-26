<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\DTO\StoreFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\Services\StoreServiceInterface;
use App\DTO\CreateStoreData;
use App\Models\Store;
use Override;

final class StoreServices implements StoreServiceInterface
{

    public function __construct(
        private StoreRepositoryInterface $storeRepo,
    ){}


    public function list(StoreFilters $filters): LengthAwarePaginator
    {
        return $this->storeRepo->findAll($filters);
    }

    public function get(string $storeID): ?Store
    {
        return $this->get($storeID);
    }

    
    public function create(CreateStoreData $data): Store
    {
        
    }
}