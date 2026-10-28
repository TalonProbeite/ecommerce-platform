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
     * Find a category by its ID.
     */
    public function findByID(string $id): ?Category;

    /**
     * Find all categories for a given store.
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

    public function findByStoreAndID(string $id, string $storeId): ?Category;

     public function createCategory(CreateCategoryData $data, string $storeId): Category;

     public function updateCategory(string $categoryId, string $storeId, UpdateCategoryData $data): Category;

     public function deleteCategory(string $categoryId, string $storeId): Category;

     public function getChildren(string $categoryId, string $storeId): Category;
}
