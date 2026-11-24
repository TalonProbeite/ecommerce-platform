<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PersistenceTestCase extends TestCase
{
    protected ?string $testSchema = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('ORDER_TEST_DATABASE')) {
            $this->markTestSkipped('Set ORDER_TEST_DATABASE to a dedicated PostgreSQL test database.');
        }

        $this->assertMatchesRegularExpression('/(?:^|_)test(?:ing)?(?:_|$)/i', getenv('ORDER_TEST_DATABASE'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame(getenv('ORDER_TEST_DATABASE'), DB::connection()->getDatabaseName());

        $this->testSchema = 'order_test_' . str_replace('-', '', (string) Str::uuid7());
        DB::statement('CREATE SCHEMA ' . $this->testSchema);
        DB::statement('SET search_path TO ' . $this->testSchema);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testSchema !== null) {
                DB::statement('SET search_path TO public');
                DB::statement('DROP SCHEMA ' . $this->testSchema . ' CASCADE');
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function createSchema(): void
    {
        foreach (glob(database_path('migrations/*.php')) as $path) {
            DB::transaction(fn () => (require $path)->up());
        }
    }

    protected function assertRejected(callable $write, string $sqlState): void
    {
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);

            return;
        }

        $this->fail('The database accepted data that violates the order contract.');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function newOrder(array $overrides = []): OrderModel
    {
        return OrderModel::create(array_merge([
            'customer_id' => (string) Str::uuid7(),
            'store_id' => (string) Str::uuid7(),
            'status' => 'created',
            'currency' => 'EUR',
            'subtotal_amount' => 0,
            'deposit_amount' => 0,
            'idempotency_key' => (string) Str::uuid7(),
            'request_fingerprint' => hash('sha256', (string) Str::uuid7()),
            'version' => 1,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function newOrderItem(OrderModel $order, array $overrides = []): OrderItemModel
    {
        return OrderItemModel::create(array_merge([
            'order_id' => $order->id,
            'product_id' => (string) Str::uuid7(),
            'product_name' => 'Drill',
            'quantity' => 1,
            'rental_start_at' => CarbonImmutable::parse('2026-12-02T10:00:00.123456+00:00'),
            'rental_end_at' => CarbonImmutable::parse('2026-12-02T12:00:00.654321+00:00'),
            'billed_hours' => 2,
            'price_per_hour' => 1000,
            'deposit_per_unit' => 5000,
            'line_total' => 2000,
            'deposit_total' => 5000,
            'item_status' => 'reserved',
        ], $overrides));
    }
}
