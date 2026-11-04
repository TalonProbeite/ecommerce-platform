<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Domain\Contracts\EventInterface;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\HasProductsException;
use App\Exceptions\HasSubcategoriesException;
use App\Infrastructure\Cache\CategoryCache;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\CategoryUpdated;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private ProductRepositoryInterface $productRepository,
        private EventPublisherInterface $eventPublisher,
        private readonly CategoryCache $categoryCache,
    ) {
    }

    public function list(string $storeId): Collection
    {
        return $this->categoryCache->rememberTree(
            $storeId,
            fn () => $this->categoryRepository->findByStore($storeId),
        );
    }

    public function getWithAncestors(string $categoryId, string $storeId): ?Category
    {
        return $this->categoryRepository->findByStoreAndID($categoryId, $storeId);
    }

    public function getChildren(string $categoryId, string $storeId): Collection
    {
        return $this->categoryRepository->findChildren($categoryId, $storeId);
    }

    public function create(string $storeId, CreateCategoryData $data): Category
    {
        $this->assertParentBelongsToStore($data->parentId, $storeId);

        $category = $this->categoryRepository->createCategory($data, $storeId);

        $this->afterMutation($storeId, CategoryCreated::fromModel($category));

        return $category;
    }

    public function update(string $categoryId, string $storeId, UpdateCategoryData $data): Category
    {
        $category = $this->findCategoryInStore($categoryId, $storeId);

        $this->assertNonNullableFields($data);

        if ($data->parentIdProvided) {
            $this->assertParentCanBeAssigned($category, $data->parentId, $storeId);
        }

        $updated = $this->categoryRepository->updateCategory($categoryId, $storeId, $data);

        $this->afterMutation($storeId, CategoryUpdated::fromModel($updated));

        return $updated;
    }

    public function delete(string $categoryId, string $storeId): void
    {
        $this->findCategoryInStore($categoryId, $storeId);

        if ($this->categoryRepository->hasSubcategories($categoryId, $storeId)) {
            throw new HasSubcategoriesException($categoryId);
        }

        if ($this->productRepository->hasByCategory($categoryId)) {
            throw new HasProductsException($categoryId);
        }

        $category = $this->categoryRepository->deleteCategory($categoryId, $storeId);

        $this->afterMutation($storeId, CategoryDeleted::fromModel($category));
    }

    private function findCategoryInStore(string $categoryId, string $storeId): Category
    {
        return $this->categoryRepository->findByStoreAndID($categoryId, $storeId)
            ?? throw new CategoryNotFoundException($categoryId);
    }

    private function assertParentBelongsToStore(?string $parentId, string $storeId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($this->categoryRepository->findByStoreAndID($parentId, $storeId) === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category does not exist in this store.',
            ]);
        }
    }

    private function assertParentCanBeAssigned(Category $category, ?string $parentId, string $storeId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $parent = $this->categoryRepository->findByStoreAndID($parentId, $storeId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category does not exist in this store.',
            ]);
        }

        if ($this->isDescendant($parent, $category->id)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be moved under one of its descendants.',
            ]);
        }
    }

    private function isDescendant(Category $candidateParent, string $categoryId): bool
    {
        $node = $candidateParent;

        while ($node !== null) {
            if ($node->id === $categoryId) {
                return true;
            }

            $node = $node->recursiveParent;
        }

        return false;
    }

    private function assertNonNullableFields(UpdateCategoryData $data): void
    {
        if ($data->nameProvided && $data->name === null) {
            throw ValidationException::withMessages([
                'name' => 'The name field cannot be null.',
            ]);
        }

        if ($data->slugProvided && $data->slug === null) {
            throw ValidationException::withMessages([
                'slug' => 'The slug field cannot be null.',
            ]);
        }
    }

    private function afterMutation(string $storeId, EventInterface $event): void
    {
        DB::afterCommit(function () use ($storeId, $event): void {
            $this->categoryCache->invalidateStore($storeId);
            $this->eventPublisher->publish($event);
        });
    }
}
