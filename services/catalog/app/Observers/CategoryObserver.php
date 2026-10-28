<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        $this->flushStore($category->store_id);
    }

    public function deleted(Category $category): void
    {
        $this->flushStore($category->store_id);
    }

    private function flushStore(string|int $storeId): void
    {
        Cache::tags(Category::cacheTag($storeId))->flush();
    }
}