<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Find a category by its ID.
     */
    public function findByID(string $id): ?Category
    {
        return Category::find($id);
    }

    public function findByStoreAndID(string $id, string $storeId): ?Category
{
    return Category::with('recursiveParent')
        ->where('id', $id)
        ->where('store_id', $storeId)
        ->first();
}
    /**
     * Find all categories for a given store.
     *
     * @return Collection<int, Category>
     */
    public function findByStore(string $storeID): Collection
    {
        return Category::whereNull('parent_id')
                ->where('store_id', $storeID)
                ->with('recursiveChildren')
                ->get();
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

    public function createCategory(CreateCategoryData $data, string $storeId): Category
    {
        $category = new Category();
        $category->fill($data->toArray());
        $category->store_id = $storeId; 
        $category->saveOrFail();
        return $category;
    }

    public function updateCategory(string $categoryId, string $storeId, UpdateCategoryData $data): Category
    {
        $category = Category::where('id', $categoryId)
            ->where('store_id', $storeId)
            ->firstOrFail();
        $category->fill($data->toArray());
        $category->saveOrFail();
        return $category;
    }

    public function deleteCategory(string $categoryId, string $storeId): Category
    {
        $category = Category::where('id', $categoryId)
            ->where('store_id', $storeId)
            ->firstOrFail();

        $category->deleteOrFail();

        return $category;
    }

    public function getChildren(string $categoryId, string $storeId): Category
    {
        return Category::where('id', $categoryId)
                ->where('store_id', $storeId)
                ->with('recursiveChildren')
                ->first();
    }
}
