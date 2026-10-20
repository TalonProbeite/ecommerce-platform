<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function findByID(string $id): ?Category
    {
        return Category::find($id);
    }

    public function findByStore(string $storeID): Collection
    {
        return Category::where('store_id', $storeID)->get();
    }


    public function findRoots(string $storeID): Collection
    {
        return Category::whereNull('parent_id')
            ->where('store_id', $storeID)
            ->get();
    }

    public function findChildren(string $parentID, string $storeID): Collection
    {
        return Category::where('parent_id', $parentID)
            ->where('store_id', $storeID)
            ->get();
    }

    public function save(Category $category): Category
    {
        $category->saveOrFail();
        return $category;
    }

    public function delete(Category $category): void
    {
        $category->deleteOrFail();
    }
}
