<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{

    public function findByID(string $id): ?Category;
    /**
     * @return Collection<int, Category>
     */
    public function findByStore(string $storeID): Collection;
    /**
     * @return Collection<int, Category>
     */
    public function findRoots(string $storeID): Collection;
    /**
     * @return Collection<int,Category>
     */
    public function findChildren(string $parentID, string $storeID): Collection;
    public function save(Category $category): Category;
    public function delete(Category $category): void;
}
