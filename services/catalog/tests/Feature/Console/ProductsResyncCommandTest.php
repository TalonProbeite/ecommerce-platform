<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;

final class ProductsResyncCommandTest extends PostgresTestCase
{
    #[Test]
    public function it_queues_one_product_created_event_for_every_live_product(): void
    {
        [$first, $second, $deleted] = $this->products();

        $this->artisan('catalog:products:resync', ['--chunk' => 1])
            ->expectsOutputToContain('Queued 2 product.created events.')
            ->assertExitCode(Command::SUCCESS);

        $payloads = $this->productCreatedPayloads();

        self::assertCount(2, $payloads);
        self::assertSame(
            $this->sortedIds([$first->id, $second->id]),
            $this->sortedIds(array_column($payloads, 'product_id')),
        );
        self::assertNotContains($deleted->id, array_column($payloads, 'product_id'));
    }

    #[Test]
    public function a_second_run_queues_the_events_again(): void
    {
        [$first, $second] = $this->products();

        $this->artisan('catalog:products:resync', ['--chunk' => 1])
            ->expectsOutputToContain('Queued 2 product.created events.')
            ->assertExitCode(Command::SUCCESS);

        $this->artisan('catalog:products:resync', ['--chunk' => 1])
            ->expectsOutputToContain('Queued 2 product.created events.')
            ->assertExitCode(Command::SUCCESS);

        $payloads = $this->productCreatedPayloads();

        self::assertCount(4, $payloads);
        self::assertSame(
            $this->sortedIds([$first->id, $second->id, $first->id, $second->id]),
            $this->sortedIds(array_column($payloads, 'product_id')),
        );
    }

    #[Test]
    public function the_event_occurred_at_is_the_product_updated_at(): void
    {
        [$product] = $this->products();
        $updatedAt = CarbonImmutable::parse('2026-05-01T12:34:56.123456+00:00');

        DB::table('products')
            ->where('id', $product->id)
            ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s.uP')]);

        $this->artisan('catalog:products:resync', ['--chunk' => 1])
            ->assertExitCode(Command::SUCCESS);

        $occurredAt = DB::table('outbox')
            ->where('aggregate_key', $product->id)
            ->value('occurred_at');

        self::assertNotNull($occurredAt);
        $actual = CarbonImmutable::parse((string) $occurredAt);

        self::assertTrue($actual->equalTo($updatedAt));
        self::assertSame('123456', $actual->format('u'));
    }

    /**
     * @return array{Product, Product, Product}
     */
    private function products(): array
    {
        $store = Store::create(['name' => 'Tools']);
        $category = Category::create([
            'store_id' => $store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);

        $first = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 1500,
            'deposit_amount' => 5000,
            'is_active' => true,
        ]);

        $second = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Hammer',
            'slug' => 'hammer',
            'price_per_hour' => 1000,
            'deposit_amount' => 3000,
            'is_active' => true,
        ]);

        $deleted = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Saw',
            'slug' => 'saw',
            'price_per_hour' => 500,
            'deposit_amount' => 1000,
            'is_active' => true,
        ]);
        $deleted->delete();

        return [$first, $second, $deleted];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productCreatedPayloads(): array
    {
        return DB::table('outbox')
            ->where('routing_key', 'product.created')
            ->orderBy('id')
            ->pluck('payload')
            ->map(static fn (mixed $payload): array => is_string($payload)
                ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR)
                : (array) $payload)
            ->all();
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private function sortedIds(array $ids): array
    {
        sort($ids);

        return array_values($ids);
    }
}
