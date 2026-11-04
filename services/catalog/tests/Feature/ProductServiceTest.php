<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Domain\Contracts\EventInterface;
use App\DTO\CreateProductData;
use App\DTO\ProductAttributeData;
use App\DTO\UpdateProductData;
use App\Exceptions\ProductCategoryNotFoundException;
use App\Exceptions\ProductHasActiveReservationsException;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Store;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockReservationRepository;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class ProductServiceTest extends PostgresTestCase
{
    private Store $store;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tools']);
        $this->category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_product_lifecycle_persists_stock_attributes_and_publishes_events(): void
    {
        $events = [];
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $publisher->shouldReceive('publish')
            ->times(6)
            ->andReturnUsing(function (EventInterface $event) use (&$events): void {
                $events[] = $event;
            });
        $service = $this->service($publisher);

        $product = $service->create($this->store->id, new CreateProductData(
            name: 'Drill',
            slug: 'drill',
            categoryId: $this->category->id,
            pricePerHour: 500,
            quantity: 4,
            attributes: [new ProductAttributeData('Power', '800 W')],
        ));

        self::assertSame(4, $product->stock->quantity);
        self::assertSame(0, $product->stock->reserved);
        self::assertSame(['Power' => '800 W'], $product->attributes->pluck('value', 'name')->all());

        $updated = $service->update(
            $this->store->id,
            $product->id,
            new UpdateProductData(name: 'Updated Drill'),
        );
        self::assertSame('Updated Drill', $updated->name);
        self::assertCount(1, $updated->attributes);

        $cleared = $service->update(
            $this->store->id,
            $product->id,
            new UpdateProductData(attributes: []),
        );
        self::assertCount(0, $cleared->attributes);

        $replaced = $service->update(
            $this->store->id,
            $product->id,
            new UpdateProductData(attributes: [new ProductAttributeData('Color', 'Red')]),
        );
        self::assertSame(['Color' => 'Red'], $replaced->attributes->pluck('value', 'name')->all());

        StockReservation::create([
            'order_id' => (string) Uuid::uuid7(),
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        try {
            $service->delete($this->store->id, $product->id);
            self::fail('Deleting a product with active reservations must fail.');
        } catch (ProductHasActiveReservationsException) {
        }

        self::assertFalse(Product::withTrashed()->findOrFail($product->id)->trashed());

        StockReservation::query()
            ->where('product_id', $product->id)
            ->update(['status' => 'returned']);

        $service->delete($this->store->id, $product->id);
        self::assertTrue(Product::withTrashed()->findOrFail($product->id)->trashed());

        $restored = $service->restore($this->store->id, $product->id);
        self::assertFalse($restored->trashed());

        self::assertInstanceOf(ProductCreated::class, $events[0]);
        self::assertInstanceOf(ProductUpdated::class, $events[1]);
        self::assertInstanceOf(ProductUpdated::class, $events[2]);
        self::assertInstanceOf(ProductUpdated::class, $events[3]);
        self::assertInstanceOf(ProductDeleted::class, $events[4]);
        self::assertInstanceOf(ProductUpdated::class, $events[5]);
    }

    public function test_operations_are_scoped_to_the_store(): void
    {
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create([
            'store_id' => $otherStore->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $publisher->shouldReceive('publish');
        $service = $this->service($publisher);

        try {
            $service->create($this->store->id, new CreateProductData(
                name: 'Foreign category',
                slug: 'foreign-category',
                categoryId: $otherCategory->id,
                pricePerHour: 100,
            ));
            self::fail('Creating a product with a category from another store must fail.');
        } catch (ProductCategoryNotFoundException) {
        }

        $product = $service->create($this->store->id, new CreateProductData(
            name: 'Drill',
            slug: 'drill',
            categoryId: $this->category->id,
            pricePerHour: 100,
        ));

        foreach ([
            [fn () => $service->get($product->id, $otherStore->id), ProductNotFoundException::class],
            [fn () => $service->update($otherStore->id, $product->id, new UpdateProductData(name: 'Hijacked')), ProductNotFoundException::class],
            [fn () => $service->delete($otherStore->id, $product->id), ProductNotFoundException::class],
            [fn () => $service->restore($otherStore->id, $product->id), ModelNotFoundException::class],
        ] as [$operation, $exception]) {
            try {
                $operation();
                self::fail('Cross-store product operations must fail.');
            } catch (\Throwable $thrown) {
                self::assertInstanceOf($exception, $thrown);
            }
        }
    }

    public function test_update_replaces_attributes_and_validates_category_ownership(): void
    {
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $publisher->shouldReceive('publish')->twice();
        $service = $this->service($publisher);

        $product = $service->create($this->store->id, new CreateProductData(
            name: 'Drill',
            slug: 'drill',
            categoryId: $this->category->id,
            pricePerHour: 500,
        ));

        $updated = $service->update($this->store->id, $product->id, new UpdateProductData(
            description: 'Updated description',
            attributes: [new ProductAttributeData('Power', '1200 W')],
        ));
        self::assertSame('Updated description', $updated->description);
        self::assertSame(['Power' => '1200 W'], $updated->attributes->pluck('value', 'name')->all());

        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create([
            'store_id' => $otherStore->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);

        try {
            $service->update($this->store->id, $product->id, new UpdateProductData(categoryId: $otherCategory->id));
            self::fail('Updating a product with another store category must fail.');
        } catch (ProductCategoryNotFoundException) {
        }
    }

    private function service(EventPublisherInterface $publisher): ProductService
    {
        return new ProductService(
            new ProductRepository(),
            new ProductAttributeRepository(),
            new CategoryRepository(),
            new StockRepository(),
            new StockReservationRepository(),
            $publisher,
            app(ProductCache::class),
        );
    }
}
