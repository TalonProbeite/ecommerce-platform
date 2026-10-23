<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Save a category.
     */
    public function save(Category $category): Category;

    /**
     * Delete a category.
     */
    public function delete(Category $category): void;
}
