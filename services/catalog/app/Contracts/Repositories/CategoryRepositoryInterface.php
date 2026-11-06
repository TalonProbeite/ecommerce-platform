<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;

interface CategoryRepositoryInterface
{
    /**
     * Find all root categories for a given store with their descendant tree.
     *
     * @return Collection<int, Category>
     */
    public function findByStore(string $storeID): Collection;

    /**
     * Find root categories for a given store.
     *
     * @return Collection<int, Category>
     */
    public function findRoots(string $storeID): Collection;

    /**
     * Find child categories for a given parent category within a store.
     *
     * @return Collection<int, Category>
     */
    public function findChildren(string $parentID, string $storeID): Collection;

    /**
     * Find a category within a store with its ancestor chain eager loaded.
     */
    public function findByStoreAndID(string $id, string $storeId): ?Category;

    public function createCategory(CreateCategoryData $data, string $storeId): Category;

    public function updateCategory(string $categoryId, string $storeId, UpdateCategoryData $data): ?Category;

    public function deleteCategory(string $categoryId, string $storeId): ?Category;

    /**
     * Lock a category row for update. Must be called inside a DB transaction.
     */
    public function lockByStoreAndId(string $categoryId, string $storeId): ?Category;

    public function hasSubcategories(string $categoryId, string $storeId): bool;
}
