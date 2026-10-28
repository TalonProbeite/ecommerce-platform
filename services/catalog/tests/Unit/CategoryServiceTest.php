<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;
use App\Exceptions\HasSubcategoriesException;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\CategoryUpdated;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_list_delegates_to_repository(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $expectedCollection = new Collection();

        $repository->shouldReceive('findByStore')
            ->once()
            ->with('store-123')
            ->andReturn($expectedCollection);

        $service = new CategoryService($repository, $publisher);
        self::assertSame($expectedCollection, $service->list('store-123'));
    }

    public function test_get_delegates_to_repository(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $category = new Category(['name' => 'Tools']);

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);

        $service = new CategoryService($repository, $publisher);
        self::assertSame($category, $service->get('cat-123', 'store-123'));
    }

    public function test_find_by_id_delegates_to_repository(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $category = new Category(['name' => 'Tools']);

        $repository->shouldReceive('findByID')
            ->once()
            ->with('cat-123')
            ->andReturn($category);

        $service = new CategoryService($repository, $publisher);
        self::assertSame($category, $service->findById('cat-123'));
    }

    public function test_create_persists_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $category = new Category([
            'name' => 'Power Tools',
            'slug' => 'power-tools',
            'parent_id' => 'parent-1',
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-new-uuid';

        $data = new CreateCategoryData(name: 'Power Tools', slug: 'power-tools', parentId: 'parent-1');

        $repository->shouldReceive('createCategory')
            ->once()
            ->with($data, 'store-123')
            ->andReturn($category);

        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (CategoryCreated $event) use ($category): bool {
                return $event->categoryId === $category->id
                    && $event->storeId === 'store-123'
                    && $event->name === 'Power Tools'
                    && $event->slug === 'power-tools'
                    && $event->parentId === 'parent-1';
            });

        $service = new CategoryService($repository, $publisher);
        $created = $service->create('store-123', $data);

        self::assertSame($category, $created);
    }

    public function test_update_persists_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $category = new Category([
            'name' => 'Updated Tools',
            'slug' => 'updated-tools',
            'parent_id' => null,
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $data = new UpdateCategoryData(name: 'Updated Tools');

        $repository->shouldReceive('updateCategory')
            ->once()
            ->with('cat-123', 'store-123', $data)
            ->andReturn($category);

        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (CategoryUpdated $event) use ($category): bool {
                return $event->categoryId === $category->id
                    && $event->storeId === 'store-123'
                    && $event->name === 'Updated Tools'
                    && $event->slug === 'updated-tools'
                    && $event->parentId === null;
            });

        $service = new CategoryService($repository, $publisher);
        $updated = $service->update('cat-123', 'store-123', $data);

        self::assertSame($category, $updated);
    }

    public function test_delete_removes_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $category = new Category([
            'name' => 'Tools',
            'slug' => 'tools',
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $repository->shouldReceive('hasSubcategories')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn(false);

        $repository->shouldReceive('deleteCategory')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);

        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (CategoryDeleted $event) use ($category): bool {
                return $event->categoryId === $category->id
                    && $event->storeId === 'store-123';
            });

        $service = new CategoryService($repository, $publisher);
        $service->delete('cat-123', 'store-123');

        // Assertion verified by Mockery expectation on $publisher->publish()
        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_exception_when_category_has_subcategories(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('hasSubcategories')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn(true);

        $repository->shouldNotReceive('deleteCategory');
        $publisher->shouldNotReceive('publish');

        $this->expectException(HasSubcategoriesException::class);

        $service = new CategoryService($repository, $publisher);
        $service->delete('cat-123', 'store-123');
    }

    public function test_get_children_delegates_to_repository(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $category = new Category(['name' => 'Parent']);

        $repository->shouldReceive('getChildren')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);

        $service = new CategoryService($repository, $publisher);
        self::assertSame($category, $service->getChildren('cat-123', 'store-123'));
    }
}
