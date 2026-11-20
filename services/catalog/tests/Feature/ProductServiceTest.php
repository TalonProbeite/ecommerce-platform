<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use App\Contracts\Outbox\OutboxWriterInterface;
use App\Outbox\OutboxRepository;
use App\Outbox\OutboxWriter;
use Carbon\CarbonImmutable;
use Tests\Support\Fakes\FakeOutboxWriter;
use Illuminate\Support\Facades\DB;
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
        $outbox = new FakeOutboxWriter();
        $service = $this->service($outbox);

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
            'order_line_id' => (string) Uuid::uuid7(),
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        self::assertFalse(Product::withTrashed()->findOrFail($product->id)->trashed());

        StockReservation::query()
            ->where('product_id', $product->id)
            ->update(['status' => 'returned']);

        $service->delete($this->store->id, $product->id);
        self::assertTrue(Product::withTrashed()->findOrFail($product->id)->trashed());

        $restored = $service->restore($this->store->id, $product->id);
        self::assertFalse($restored->trashed());

        $events = $outbox->events();

        self::assertInstanceOf(ProductCreated::class, $events[0]);
        self::assertInstanceOf(ProductUpdated::class, $events[1]);
        self::assertInstanceOf(ProductUpdated::class, $events[2]);
        self::assertInstanceOf(ProductUpdated::class, $events[3]);
        self::assertInstanceOf(ProductDeleted::class, $events[4]);
        self::assertInstanceOf(ProductUpdated::class, $events[5]);
    }

    public function test_get_from_another_store_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();
        $otherStore = Store::create(['name' => 'Other']);

        $this->expectException(ProductNotFoundException::class);

        $service->get($product->id, $otherStore->id);
    }

    public function test_update_from_another_store_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();
        $otherStore = Store::create(['name' => 'Other']);

        $this->expectException(ProductNotFoundException::class);

        $service->update($otherStore->id, $product->id, new UpdateProductData(name: 'Hijacked'));
    }

    public function test_delete_from_another_store_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();
        $otherStore = Store::create(['name' => 'Other']);

        $this->expectException(ProductNotFoundException::class);

        $service->delete($otherStore->id, $product->id);
    }

    public function test_restore_from_another_store_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();
        $service->delete($this->store->id, $product->id);
        $otherStore = Store::create(['name' => 'Other']);

        $this->expectException(ProductNotFoundException::class);

        $service->restore($otherStore->id, $product->id);
    }

    public function test_delete_with_active_reservations_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();

        StockReservation::create([
            'order_id' => (string) Uuid::uuid7(),
            'order_line_id' => (string) Uuid::uuid7(),
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        $this->expectException(ProductHasActiveReservationsException::class);

        $service->delete($this->store->id, $product->id);
    }

    public function test_update_replaces_attributes(): void
    {
        $service = $this->service(new FakeOutboxWriter());

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
    }

    public function test_update_with_another_store_category_is_rejected(): void
    {
        $service = $this->serviceWithEmptyOutbox();
        $product = $this->createDrill();
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create([
            'store_id' => $otherStore->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);

        $this->expectException(ProductCategoryNotFoundException::class);

        $service->update($this->store->id, $product->id, new UpdateProductData(categoryId: $otherCategory->id));
    }

    public function test_outbox_row_is_rolled_back_when_the_transaction_fails(): void
    {
        $outbox = new class () implements OutboxWriterInterface {
            public function enqueue(\App\Domain\Contracts\EventInterface $event, CarbonImmutable $occurredAt): string
            {
                $eventId = (new OutboxWriter(new OutboxRepository()))->enqueue($event, $occurredAt);

                throw new \RuntimeException('Simulated failure after the outbox insert.');
            }
        };

        $service = $this->service($outbox);

        try {
            $service->create($this->store->id, new CreateProductData(
                name: 'Rollback Drill',
                slug: 'rollback-drill',
                categoryId: $this->category->id,
                pricePerHour: 500,
                quantity: 1,
            ));
            self::fail('Expected the simulated failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated failure after the outbox insert.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('outbox')->count());
        self::assertFalse(Product::withTrashed()->where('slug', 'rollback-drill')->exists());
    }

    private function service(OutboxWriterInterface $outbox): ProductService
    {
        return new ProductService(
            new ProductRepository(),
            new ProductAttributeRepository(),
            new CategoryRepository(),
            new StockRepository(),
            new StockReservationRepository(),
            $outbox,
            app(ProductCache::class),
        );
    }

    private function serviceWithEmptyOutbox(): ProductService
    {
        return $this->service(new FakeOutboxWriter());
    }

    private function createDrill(): Product
    {
        $service = $this->service(new FakeOutboxWriter());

        return $service->create($this->store->id, new CreateProductData(
            name: 'Drill',
            slug: 'drill-' . Uuid::uuid7(),
            categoryId: $this->category->id,
            pricePerHour: 500,
            quantity: 3,
        ));
    }
}
