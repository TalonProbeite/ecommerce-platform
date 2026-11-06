<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\QuantityBelowReservedException;
use App\Exceptions\ReservedUnderflowException;
use App\Exceptions\StockConcurrencyException;
use App\Exceptions\StockNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\StockChanged;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    private ProductCache $productCache;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->productCache = $this->cache();

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

    public function test_get_returns_stock_or_throws_when_missing(): void
    {
        $stock = $this->stock(10, 2);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $repository->shouldReceive('findByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($stock);

        self::assertSame($stock, $this->service($repository)->get('store-123', 'product-123'));

        $missing = Mockery::mock(StockRepositoryInterface::class);
        $missing->shouldReceive('findByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturnNull();

        $this->expectException(StockNotFoundException::class);
        $this->service($missing)->get('store-123', 'product-123');
    }

    public function test_set_quantity_updates_stock_and_publishes_event(): void
    {
        $stock = $this->stock(10, 2, fn (Stock $model) => $model->forceFill(['quantity' => 7]));
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($stock);
        $repository->shouldReceive('setQuantityIfNotBelowReserved')
            ->once()
            ->with('stock-123', 7)
            ->andReturn(1);
        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(fn (StockChanged $event): bool => $event->productId === 'product-123'
                && $event->storeId === 'store-123'
                && $event->quantity === 7
                && $event->reserved === 2);

        self::assertSame($stock, $this->service($repository, $publisher)->setQuantity('store-123', 'product-123', 7));
    }

    public function test_set_quantity_rejects_values_below_reserved(): void
    {
        $stock = $this->stock(10, 4);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldNotReceive('setQuantityIfNotBelowReserved');
        $publisher->shouldNotReceive('publish');

        $this->expectException(QuantityBelowReservedException::class);

        $this->service($repository, $publisher)->setQuantity('store-123', 'product-123', 3);
    }

    public function test_set_quantity_detects_concurrent_update(): void
    {
        $stock = $this->stock(10, 0);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldReceive('setQuantityIfNotBelowReserved')
            ->once()
            ->with('stock-123', 5)
            ->andReturn(0);
        $publisher->shouldNotReceive('publish');

        $this->expectException(StockConcurrencyException::class);

        $this->service($repository, $publisher)->setQuantity('store-123', 'product-123', 5);
    }

    public function test_reserve_units_increases_reserved_and_publishes_event(): void
    {
        $stock = $this->stock(10, 1, fn (Stock $model) => $model->forceFill(['reserved' => 4]));
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldReceive('adjustReserved')
            ->once()
            ->with('stock-123', 3)
            ->andReturn(1);
        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(fn (StockChanged $event): bool => $event->quantity === 10 && $event->reserved === 4);

        self::assertSame($stock, $this->service($repository, $publisher)->reserveUnits('store-123', 'product-123', 3));
    }

    public function test_reserve_units_rejects_insufficient_availability(): void
    {
        $stock = $this->stock(10, 8);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldNotReceive('adjustReserved');
        $publisher->shouldNotReceive('publish');

        $this->expectException(InsufficientStockException::class);

        $this->service($repository, $publisher)->reserveUnits('store-123', 'product-123', 3);
    }

    public function test_reserve_rejects_a_non_positive_quantity(): void
    {
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $repository->shouldNotReceive('lockByProductId');

        $this->expectException(InvalidArgumentException::class);

        $this->service($repository)->reserveUnits('store-123', 'product-123', 0);
    }

    public function test_release_rejects_a_non_positive_quantity(): void
    {
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $repository->shouldNotReceive('lockByProductId');

        $this->expectException(InvalidArgumentException::class);

        $this->service($repository)->releaseUnits('store-123', 'product-123', -1);
    }

    public function test_set_quantity_flushes_only_the_product_card(): void
    {
        $stock = $this->stock(10, 2, fn (Stock $model) => $model->forceFill(['quantity' => 7]));
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);
        $cache = $this->cache();
        $this->productCache = $cache;

        $cache->rememberCard('admin', 'product-123', fn (): string => 'old-card');
        $cache->rememberCard('admin', 'product-999', fn (): string => 'other-card');
        $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'old-list');

        $repository->shouldReceive('lockByProductId')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn($stock);
        $repository->shouldReceive('setQuantityIfNotBelowReserved')
            ->once()
            ->with('stock-123', 7)
            ->andReturn(1);
        $publisher->shouldReceive('publish')->once();

        $this->service($repository, $publisher)->setQuantity('store-123', 'product-123', 7);

        self::assertSame('new-card', $cache->rememberCard('admin', 'product-123', fn (): string => 'new-card'));
        self::assertSame('other-card', $cache->rememberCard('admin', 'product-999', fn (): string => 'refreshed-other-card'));
        self::assertSame('old-list', $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'new-list'));
    }

    public function test_release_units_decreases_reserved_and_publishes_event(): void
    {
        $stock = $this->stock(10, 4, fn (Stock $model) => $model->forceFill(['reserved' => 1]));
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldReceive('adjustReserved')
            ->once()
            ->with('stock-123', -3)
            ->andReturn(1);
        $publisher->shouldReceive('publish')
            ->once()
            ->withArgs(fn (StockChanged $event): bool => $event->quantity === 10 && $event->reserved === 1);

        self::assertSame($stock, $this->service($repository, $publisher)->releaseUnits('store-123', 'product-123', 3));
    }

    public function test_release_units_rejects_underflow(): void
    {
        $stock = $this->stock(10, 2);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldNotReceive('adjustReserved');
        $publisher->shouldNotReceive('publish');

        $this->expectException(ReservedUnderflowException::class);

        $this->service($repository, $publisher)->releaseUnits('store-123', 'product-123', 3);
    }

    public function test_adjust_reserved_detects_concurrent_update(): void
    {
        $stock = $this->stock(10, 0);
        $repository = Mockery::mock(StockRepositoryInterface::class);
        $publisher = Mockery::mock(EventPublisherInterface::class);

        $repository->shouldReceive('lockByProductId')->once()->andReturn($stock);
        $repository->shouldReceive('adjustReserved')
            ->once()
            ->with('stock-123', 2)
            ->andReturn(0);
        $publisher->shouldNotReceive('publish');

        $this->expectException(StockConcurrencyException::class);

        $this->service($repository, $publisher)->reserveUnits('store-123', 'product-123', 2);
    }

    private function service(
        StockRepositoryInterface $repository,
        ?EventPublisherInterface $publisher = null,
    ): StockService {
        return new StockService(
            $repository,
            $publisher ?? Mockery::mock(EventPublisherInterface::class),
            $this->productCache,
        );
    }

    private function cache(): ProductCache
    {
        return new ProductCache(
            300,
            600,
            86400,
            Mockery::mock(ProductRepositoryInterface::class),
        );
    }

    /**
     * @param (callable(Stock): void)|null $afterRefresh
     */
    private function stock(int $quantity, int $reserved, ?callable $afterRefresh = null): Stock
    {
        $stock = new class () extends Stock {
            /** @var (callable(Stock): void)|null */
            public $onRefresh;

            public function refresh()
            {
                if ($this->onRefresh !== null) {
                    ($this->onRefresh)($this);
                }

                return $this;
            }
        };
        $stock->forceFill([
            'id' => 'stock-123',
            'product_id' => 'product-123',
            'store_id' => 'store-123',
            'quantity' => $quantity,
            'reserved' => $reserved,
        ]);
        $stock->onRefresh = $afterRefresh;

        return $stock;
    }
}
