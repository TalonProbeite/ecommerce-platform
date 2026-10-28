<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\CategoryServiceInterface;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use App\Contracts\Messaging\EventPublisherInterface;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryUpdated;
use App\Messaging\Events\CategoryDeleted;
use Override;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private EventPublisherInterface $eventPublisher,

    ){}

    public function list(string $storeID): Collection
    {
        return $this->categoryRepository->findByStore($storeID);
    }

    public function get(string $categoryId, string $storeId): ?Category
    {
        return $this->categoryRepository->findByStoreAndID($categoryId, $storeId);
    }

    public function findById(string $categoryId): ?Category
    {
        return $this->categoryRepository->findByID($categoryId);
    }

    public function create(string $storeId, CreateCategoryData $data): Category
    {
        $category =  $this->categoryRepository->createCategory($data, $storeId);
        $event = CategoryCreated::fromModel($category);
        $this->eventPublisher->publish($event);

        return $category;
    }

    public function update(string $categoryId, string $storeId, UpdateCategoryData $data): Category
    {
         $category = $this->categoryRepository->updateCategory($categoryId, $storeId, $data);
         $event = CategoryUpdated::fromModel($category);
         $this->eventPublisher->publish($event);

         return $category;
    }    

    public function delete(string $categoryId, string $storeId): void
    {
        $category = $this->categoryRepository->deleteCategory($categoryId, $storeId);

        $event= CategoryDeleted::fromModel($category);

        $this->eventPublisher->publish($event);
    }

    public function getChildren(string $categoryId, string $storeId): Category
    {
        return $this->categoryRepository->getChildren($categoryId, $storeId);
    }
}