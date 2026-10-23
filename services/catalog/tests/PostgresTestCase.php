<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

abstract class PostgresTestCase extends TestCase
{
    protected ?string $testSchema = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Opt in explicitly; each test owns a separate, initially empty PostgreSQL schema.
        if (! getenv('CATALOG_TEST_DATABASE')) {
            $this->markTestSkipped('Set CATALOG_TEST_DATABASE to a dedicated PostgreSQL test database.');
        }

        $this->assertMatchesRegularExpression('/(?:^|_)test(?:ing)?(?:_|$)/i', getenv('CATALOG_TEST_DATABASE'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame(getenv('CATALOG_TEST_DATABASE'), DB::connection()->getDatabaseName());

        $this->testSchema = 'catalog_test_' . str_replace('-', '', (string) Uuid::uuid7());
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

        $this->fail('The database accepted data that violates the catalog contract.');
    }
}
