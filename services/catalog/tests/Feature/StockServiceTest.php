<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Domain\Contracts\EventInterface;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\QuantityBelowReservedException;
use App\Exceptions\ReservedUnderflowException;
use App\Exceptions\StockNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\StockChanged;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Repositories\StockRepository;
use App\Services\StockService;
use InvalidArgumentException;
use Mockery;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class StockServiceTest extends PostgresTestCase
{
    private Store $store;
    private Product $product;
    private Stock $stock;
    /** @var list<EventInterface> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tools']);
        $category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 500,
        ]);
        $this->stock = Stock::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 10,
            'reserved' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_returns_stock_scoped_to_store(): void
    {
        $service = $this->service();

        self::assertTrue($service->get($this->store->id, $this->product->id)->is($this->stock));

        $this->expectException(StockNotFoundException::class);
        $service->get((string) Uuid::uuid7(), $this->product->id);
    }

    public function test_set_quantity_updates_stock_and_publishes_event(): void
    {
        $updated = $this->service()->setQuantity($this->store->id, $this->product->id, 15);

        self::assertSame(15, $updated->quantity);
        self::assertSame(2, $updated->reserved);
        self::assertSame(13, $updated->available);
        self::assertCount(1, $this->events);
        self::assertInstanceOf(StockChanged::class, $this->events[0]);
        self::assertSame(15, $this->events[0]->quantity);
        self::assertSame(2, $this->events[0]->reserved);
    }

    public function test_set_quantity_rejects_values_below_reserved(): void
    {
        try {
            $this->service()->setQuantity($this->store->id, $this->product->id, 1);
            self::fail('Setting quantity below reserved must fail.');
        } catch (QuantityBelowReservedException $exception) {
            self::assertSame(2, $exception->reserved);
            self::assertSame(1, $exception->requested);
        }

        self::assertSame(10, $this->stock->fresh()->quantity);
        self::assertCount(0, $this->events);
    }

    public function test_reserve_and_release_units_update_reserved_and_publish_events(): void
    {
        $service = $this->service();

        $reserved = $service->reserveUnits($this->store->id, $this->product->id, 3);
        self::assertSame(5, $reserved->reserved);
        self::assertSame(5, $reserved->available);

        $released = $service->releaseUnits($this->store->id, $this->product->id, 4);
        self::assertSame(1, $released->reserved);
        self::assertSame(9, $released->available);

        self::assertCount(2, $this->events);
        self::assertSame([5, 1], array_map(fn (StockChanged $event): int => $event->reserved, $this->events));
    }

    public function test_reserve_units_rejects_insufficient_stock(): void
    {
        $this->expectException(InsufficientStockException::class);

        try {
            $this->service()->reserveUnits($this->store->id, $this->product->id, 9);
        } finally {
            self::assertSame(2, $this->stock->fresh()->reserved);
            self::assertCount(0, $this->events);
        }
    }

    public function test_release_units_rejects_underflow(): void
    {
        $this->expectException(ReservedUnderflowException::class);

        try {
            $this->service()->releaseUnits($this->store->id, $this->product->id, 3);
        } finally {
            self::assertSame(2, $this->stock->fresh()->reserved);
            self::assertCount(0, $this->events);
        }
    }

    public function test_non_positive_quantities_are_rejected(): void
    {
        $service = $this->service();

        foreach ([0, -1] as $quantity) {
            try {
                $service->reserveUnits($this->store->id, $this->product->id, $quantity);
                self::fail('Reserving a non-positive quantity must fail.');
            } catch (InvalidArgumentException) {
            }

            try {
                $service->releaseUnits($this->store->id, $this->product->id, $quantity);
                self::fail('Releasing a non-positive quantity must fail.');
            } catch (InvalidArgumentException) {
            }
        }

        self::assertSame(2, $this->stock->fresh()->reserved);
        self::assertCount(0, $this->events);
    }

    public function test_missing_stock_is_rejected_for_mutations(): void
    {
        $service = $this->service();
        $productId = (string) Uuid::uuid7();

        foreach ([
            fn () => $service->setQuantity($this->store->id, $productId, 1),
            fn () => $service->reserveUnits($this->store->id, $productId, 1),
            fn () => $service->releaseUnits($this->store->id, $productId, 1),
        ] as $operation) {
            try {
                $operation();
                self::fail('Mutating missing stock must fail.');
            } catch (StockNotFoundException) {
            }
        }

        self::assertCount(0, $this->events);
    }

    private function service(): StockService
    {
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $publisher->shouldReceive('publish')
            ->andReturnUsing(function (EventInterface $event): void {
                $this->events[] = $event;
            });

        return new StockService(new StockRepository(), $publisher, app(ProductCache::class));
    }
}
