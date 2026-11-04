<?php

declare(strict_types=1);

namespace App\Observers;

use App\Infrastructure\Cache\ProductCache;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class CategoryObserver
{
    public function __construct(
        private readonly ProductCache $productCache,
    ) {
    }

    public function updated(Category $category): void
    {
        $this->forgetCategoryCards($category);
    }

    public function deleted(Category $category): void
    {
        $this->forgetCategoryCards($category);
    }

    public function restored(Category $category): void
    {
        $this->forgetCategoryCards($category);
    }

    private function forgetCategoryCards(Category $category): void
    {
        DB::afterCommit(function () use ($category): void {
            $this->productCache->forgetCategoryCards($category->id);
        });
    }
}
