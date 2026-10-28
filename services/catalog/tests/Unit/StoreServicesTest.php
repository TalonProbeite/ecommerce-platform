<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\StoreRepositoryInterface;
use App\DTO\UpdateStoreData;
use App\Exceptions\StoreHasActiveRentalsException;
use App\Models\Store;
use App\Services\StoreServices;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class StoreServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_delegates_to_repository(): void
    {
        $store = new Store(['name' => 'Tools']);
        $repository = Mockery::mock(StoreRepositoryInterface::class);
        $repository->shouldReceive('findByID')->once()->with('store-id')->andReturn($store);

        self::assertSame($store, (new StoreServices($repository))->get('store-id'));
    }

    public function test_update_rejects_explicit_null_for_non_nullable_fields(): void
    {
        $repository = Mockery::mock(StoreRepositoryInterface::class);
        $repository->shouldNotReceive('updateStore');

        $this->expectException(ValidationException::class);

        (new StoreServices($repository))->update('store-id', new UpdateStoreData(name: null, nameProvided: true));
    }

    public function test_delete_removes_store_when_no_active_rentals(): void
    {
        $store = new Store(['name' => 'Tools']);
        $repository = Mockery::mock(StoreRepositoryInterface::class);
        $repository->shouldReceive('hasActiveRentals')->once()->with('store-id')->andReturn(false);
        $repository->shouldReceive('deleteStore')->once()->with('store-id')->andReturn($store);

        (new StoreServices($repository))->delete('store-id');
        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_exception_when_active_rentals_exist(): void
    {
        $repository = Mockery::mock(StoreRepositoryInterface::class);
        $repository->shouldReceive('hasActiveRentals')->once()->with('store-id')->andReturn(true);
        $repository->shouldNotReceive('deleteStore');

        $this->expectException(StoreHasActiveRentalsException::class);

        (new StoreServices($repository))->delete('store-id');
    }
}
