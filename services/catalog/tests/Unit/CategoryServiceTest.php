<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\Fakes\FakeOutboxWriter;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\DTO\CreateCategoryData;
use App\DTO\UpdateCategoryData;
use App\Exceptions\CategoryParentDescendantException;
use App\Exceptions\CategoryParentSelfException;
use App\Exceptions\HasProductsException;
use App\Exceptions\HasSubcategoriesException;
use App\Exceptions\NonNullableFieldException;
use App\Exceptions\ParentCategoryNotFoundException;
use App\Infrastructure\Cache\CategoryCache;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\CategoryUpdated;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());
        DB::shouldReceive('afterCommit')
            ->andReturnUsing(fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_list_delegates_to_repository_and_caches_the_tree(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();
        $expectedCollection = new Collection();

        $repository->shouldReceive('findByStore')
            ->once()
            ->with('store-123')
            ->andReturn($expectedCollection);

        $service = $this->service($repository, $productRepository, $outbox);
        self::assertSame($expectedCollection, $service->list('store-123'));
        self::assertSame($expectedCollection, $service->list('store-123'));
    }

    public function test_get_with_ancestors_delegates_to_repository(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();
        $category = new Category(['name' => 'Tools']);

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);

        $service = $this->service($repository, $productRepository, $outbox);
        self::assertSame($category, $service->getWithAncestors('cat-123', 'store-123'));
    }

    public function test_get_children_returns_immediate_children(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();
        $children = new Collection([new Category(['name' => 'Child'])]);

        $repository->shouldReceive('findChildren')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($children);

        $service = $this->service($repository, $productRepository, $outbox);
        self::assertSame($children, $service->getChildren('cat-123', 'store-123'));
    }

    public function test_create_persists_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $parent = new Category([
            'name' => 'Parent',
            'slug' => 'parent',
            'store_id' => 'store-123',
        ]);
        $parent->id = 'parent-1';

        $category = new Category([
            'name' => 'Power Tools',
            'slug' => 'power-tools',
            'parent_id' => 'parent-1',
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-new-uuid';

        $data = new CreateCategoryData(name: 'Power Tools', slug: 'power-tools', parentId: 'parent-1');

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('parent-1', 'store-123')
            ->andReturn($parent);
        $repository->shouldReceive('createCategory')
            ->once()
            ->with($data, 'store-123')
            ->andReturn($category);

        $service = $this->service($repository, $productRepository, $outbox);
        $created = $service->create('store-123', $data);

        self::assertSame($category, $created);
        self::assertCount(1, $outbox->events());
        self::assertInstanceOf(CategoryCreated::class, $outbox->events()[0]);
    }

    public function test_create_rejects_missing_parent(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('missing', 'store-123')
            ->andReturn(null);
        $repository->shouldNotReceive('createCategory');

        $this->expectException(ParentCategoryNotFoundException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->create('store-123', new CreateCategoryData(
            name: 'Tools',
            slug: 'tools',
            parentId: 'missing',
        ));
    }

    public function test_update_persists_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $existing = new Category([
            'name' => 'Tools',
            'slug' => 'tools',
            'store_id' => 'store-123',
        ]);
        $existing->id = 'cat-123';

        $category = new Category([
            'name' => 'Updated Tools',
            'slug' => 'updated-tools',
            'parent_id' => null,
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $data = new UpdateCategoryData(name: 'Updated Tools');

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($existing);
        $repository->shouldReceive('updateCategory')
            ->once()
            ->with('cat-123', 'store-123', $data)
            ->andReturn($category);

        $service = $this->service($repository, $productRepository, $outbox);
        $updated = $service->update('cat-123', 'store-123', $data);

        self::assertSame($category, $updated);
        self::assertCount(1, $outbox->events());
        self::assertInstanceOf(CategoryUpdated::class, $outbox->events()[0]);
    }

    public function test_update_rejects_explicit_null_for_non_nullable_fields(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $existing = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $existing->id = 'cat-123';

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($existing);
        $repository->shouldNotReceive('updateCategory');

        $this->expectException(NonNullableFieldException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->update('cat-123', 'store-123', new UpdateCategoryData(name: null, nameProvided: true));
    }

    public function test_update_rejects_setting_category_as_its_own_parent(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $existing = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $existing->id = 'cat-123';

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($existing);
        $repository->shouldNotReceive('updateCategory');

        $this->expectException(CategoryParentSelfException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->update('cat-123', 'store-123', new UpdateCategoryData(parentId: 'cat-123'));
    }

    public function test_update_rejects_setting_descendant_as_parent(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $category = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $category->id = 'cat-123';

        $descendant = new Category(['name' => 'Child', 'slug' => 'child', 'store_id' => 'store-123']);
        $descendant->id = 'cat-456';
        $descendant->setRelation('recursiveParent', $category);

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);
        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-456', 'store-123')
            ->andReturn($descendant);
        $repository->shouldNotReceive('updateCategory');

        $this->expectException(CategoryParentDescendantException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->update('cat-123', 'store-123', new UpdateCategoryData(parentId: 'cat-456'));
    }

    public function test_update_allows_explicit_null_parent_to_move_category_to_root(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $existing = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $existing->id = 'cat-123';

        $category = new Category([
            'name' => 'Tools',
            'slug' => 'tools',
            'parent_id' => null,
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $data = new UpdateCategoryData(parentId: null, parentIdProvided: true);

        $repository->shouldReceive('findByStoreAndID')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($existing);
        $repository->shouldReceive('updateCategory')
            ->once()
            ->with('cat-123', 'store-123', $data)
            ->andReturn($category);

        $service = $this->service($repository, $productRepository, $outbox);
        self::assertSame($category, $service->update('cat-123', 'store-123', $data));
    }

    public function test_delete_removes_category_and_publishes_event(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $category = new Category([
            'name' => 'Tools',
            'slug' => 'tools',
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $repository->shouldReceive('lockByStoreAndId')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);
        $repository->shouldReceive('hasSubcategories')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn(false);
        $productRepository->shouldReceive('hasByCategory')
            ->once()
            ->with('cat-123')
            ->andReturn(false);
        $repository->shouldReceive('deleteCategory')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->delete('cat-123', 'store-123');

        self::assertCount(1, $outbox->events());
        self::assertInstanceOf(CategoryDeleted::class, $outbox->events()[0]);
    }

    public function test_delete_throws_exception_when_category_has_subcategories(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $category = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $category->id = 'cat-123';

        $repository->shouldReceive('lockByStoreAndId')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);
        $repository->shouldReceive('hasSubcategories')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn(true);
        $productRepository->shouldNotReceive('hasByCategory');
        $repository->shouldNotReceive('deleteCategory');

        $this->expectException(HasSubcategoriesException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->delete('cat-123', 'store-123');
    }

    public function test_delete_throws_exception_when_category_has_products(): void
    {
        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $category = new Category(['name' => 'Tools', 'slug' => 'tools', 'store_id' => 'store-123']);
        $category->id = 'cat-123';

        $repository->shouldReceive('lockByStoreAndId')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn($category);
        $repository->shouldReceive('hasSubcategories')
            ->once()
            ->with('cat-123', 'store-123')
            ->andReturn(false);
        $productRepository->shouldReceive('hasByCategory')
            ->once()
            ->with('cat-123')
            ->andReturn(true);
        $repository->shouldNotReceive('deleteCategory');

        $this->expectException(HasProductsException::class);

        $service = $this->service($repository, $productRepository, $outbox);
        $service->delete('cat-123', 'store-123');
    }

    public function test_create_invalidates_the_category_tree_cache(): void
    {
        $categoryCache = new CategoryCache(1800, 86400);
        $categoryCache->rememberTree('store-123', fn (): string => 'cached');

        $repository = Mockery::mock(CategoryRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $outbox = new FakeOutboxWriter();

        $category = new Category([
            'name' => 'Tools',
            'slug' => 'tools',
            'store_id' => 'store-123',
        ]);
        $category->id = 'cat-123';

        $data = new CreateCategoryData(name: 'Tools', slug: 'tools');

        $repository->shouldReceive('createCategory')
            ->once()
            ->with($data, 'store-123')
            ->andReturn($category);

        (new CategoryService($repository, $productRepository, $outbox, $categoryCache))
            ->create('store-123', $data);

        self::assertSame(
            'refreshed',
            $categoryCache->rememberTree('store-123', fn (): string => 'refreshed'),
        );
    }

    private function service(
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository,
        FakeOutboxWriter $outbox,
    ): CategoryService {
        return new CategoryService(
            $categoryRepository,
            $productRepository,
            $outbox,
            new CategoryCache(1800, 86400),
        );
    }
}
