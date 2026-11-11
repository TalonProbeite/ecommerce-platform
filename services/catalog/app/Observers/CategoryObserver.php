<?php

declare(strict_types=1);

namespace App\Observers;

use App\Infrastructure\Cache\CategoryCache;
use App\Infrastructure\Cache\ProductCache;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class CategoryObserver
{
    public function __construct(
        private readonly ProductCache $productCache,
        private readonly CategoryCache $categoryCache,
    ) {
    }

    public function updated(Category $category): void
    {
        $this->invalidateCategory($category);
    }

    public function deleted(Category $category): void
    {
        $this->invalidateCategory($category);
    }

    public function restored(Category $category): void
    {
        $this->invalidateCategory($category);
    }

    private function invalidateCategory(Category $category): void
    {
        DB::afterCommit(function () use ($category): void {
            $this->categoryCache->invalidateStore($category->store_id);
            $this->productCache->invalidateCategory($category->id);
        });
    }
}
