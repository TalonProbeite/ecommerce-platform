<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mutations reject missing entities, invalid input, or invalid store ownership
 * as business-operation errors. Update DTO fields marked absent stay unchanged;
 * explicit null is accepted only for nullable schema fields.
 */
interface CategoryServiceInterface
{
    /** @return Collection<int, Category> */
    public function list(string $storeId): Collection;

    /** Return null when absent within the requested scope. */
    public function get(string $categoryId, string $storeId): ?Category;

    public function create(string $storeId, CreateCategoryData $data): Category;

    public function update(string $categoryId, string $storeId, UpdateCategoryData $data): Category;

    /**
     * Check child categories and related products before soft deletion; reject blocking dependencies.
     * Enforce business checks explicitly: foreign keys do not guard soft deletion.
     */
    public function delete(string $categoryId, string $storeId): void;
}
