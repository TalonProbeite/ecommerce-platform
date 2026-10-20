<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Find a category by its ID.
     */
    public function findByID(string $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Find all categories for a given store.
     *
     * @return Collection<int, Category>
     */
    public function findByStore(string $storeID): Collection
    {
        return Category::where('store_id', $storeID)->get();
    }

    /**
     * Find root categories for a given store.
     *
     * @return Collection<int, Category>
     */
    public function findRoots(string $storeID): Collection
    {
        return Category::whereNull('parent_id')
            ->where('store_id', $storeID)
            ->get();
    }

    /**
     * Find child categories for a given parent category within a store.
     *
     * @return Collection<int, Category>
     */
    public function findChildren(string $parentID, string $storeID): Collection
    {
        return Category::where('parent_id', $parentID)
            ->where('store_id', $storeID)
            ->get();
    }

    /**
     * Save a category.
     */
    public function save(Category $category): Category
    {
        $category->saveOrFail();
        return $category;
    }

    /**
     * Delete a category.
     */
    public function delete(Category $category): void
    {
        $category->deleteOrFail();
    }
}
