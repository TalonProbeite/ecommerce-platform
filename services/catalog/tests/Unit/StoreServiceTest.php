<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Infrastructure\Cache\ProductCache;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class StoreServiceTest extends TestCase
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

    public function test_get_delegates_to_repository(): void
    {
        $store = new Store(['name' => 'Tools']);
        $storeRepository = Mockery::mock(StoreRepositoryInterface::class);
        $storeRepository->shouldReceive('findByID')->once()->with('store-id')->andReturn($store);
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);

        self::assertSame($store, $this->service($storeRepository, $reservationRepository)->get('store-id'));
    }

    public function test_update_delegates_to_repository_without_service_level_validation(): void
    {
        $store = new Store(['name' => 'Tools']);
        $store->id = 'store-id';
        $data = new UpdateStoreData(name: null, nameProvided: true);
        $storeRepository = Mockery::mock(StoreRepositoryInterface::class);
        $storeRepository->shouldReceive('findByID')->once()->with('store-id')->andReturn($store);
        $storeRepository->shouldReceive('updateStore')->once()->with('store-id', $data)->andReturn($store);
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);

        self::assertSame($store, $this->service($storeRepository, $reservationRepository)->update('store-id', $data));
    }

    public function test_delete_removes_store_when_no_active_reservations(): void
    {
        $store = new Store(['name' => 'Tools']);
        $storeRepository = Mockery::mock(StoreRepositoryInterface::class);
        $storeRepository->shouldReceive('lockById')->once()->with('store-id')->andReturn($store);
        $storeRepository->shouldReceive('deleteStore')->once()->with('store-id')->andReturn($store);
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);
        $reservationRepository->shouldReceive('hasActiveByStore')->once()->with('store-id')->andReturn(false);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('findIdsByStore')->once()->with('store-id')->andReturn([]);

        $this->service($storeRepository, $reservationRepository, $productRepository)->delete('store-id');
        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_exception_when_active_reservations_exist(): void
    {
        $storeRepository = Mockery::mock(StoreRepositoryInterface::class);
        $storeRepository->shouldReceive('lockById')->once()->with('store-id')->andReturn(new Store());
        $storeRepository->shouldNotReceive('deleteStore');
        $reservationRepository = Mockery::mock(StockReservationRepositoryInterface::class);
        $reservationRepository->shouldReceive('hasActiveByStore')->once()->with('store-id')->andReturn(true);

        $this->expectException(StoreHasActiveRentalsException::class);

        $this->service($storeRepository, $reservationRepository)->delete('store-id');
    }

    private function service(
        StoreRepositoryInterface $storeRepository,
        StockReservationRepositoryInterface $stockReservationRepository,
        ?ProductRepositoryInterface $productRepository = null,
    ): StoreService {
        return new StoreService(
            $storeRepository,
            $stockReservationRepository,
            $productRepository ?? Mockery::mock(ProductRepositoryInterface::class),
            new ProductCache(300, 600, 86400, Mockery::mock(ProductRepositoryInterface::class)),
        );
    }
}
