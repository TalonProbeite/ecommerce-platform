<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\ImportDemoCatalog;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class DemoCatalogTest extends TestCase
{
    /** @return array<string, mixed> */
    private function manual(): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/catalog-demo-manual.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    private function source(): array
    {
        $products = [];
        foreach ($this->manual()['imports'] as $item) {
            $products[] = ['id' => $item['id'], 'title' => $item['title'], 'category' => $item['source_category'],
                'brand' => 'Fixture brand', 'price' => 999999, 'stock' => 0,
                'images' => ['https://cdn.dummyjson.com/product-images/example/1.webp']];
        }
        return $products;
    }

    public function test_normalization_is_order_independent_and_does_not_use_sale_price_or_source_stock(): void
    {
        Http::preventStrayRequests();
        $command = new ImportDemoCatalog();
        $source = $this->source();
        $source[] = ['id' => 1, 'title' => 'Mascara', 'category' => 'beauty'];
        $dataset = $command->normalize($source, $this->manual());
        $changed = array_reverse($source);
        foreach ($changed as &$item) {
            $item['price'] = 0.01;
            $item['stock'] = 9999;
        }
        unset($item);
        self::assertSame($dataset, $command->normalize($changed, $this->manual()));
        self::assertCount(48, $dataset['products']);
        $laptop = array_values(array_filter($dataset['products'], fn ($p) => $p['slug'] === 'demo-dummyjson-78'))[0];
        self::assertSame(15000, $laptop['price_per_hour']);
        self::assertSame(5000000, $laptop['deposit_amount']);
        self::assertSame(['quantity' => 3, 'reserved' => 0], $laptop['stock']);
        self::assertSame([], $laptop['images']);
        self::assertCount(1, $laptop['source']['image_urls']);
        self::assertStringStartsWith('DEMO ', $laptop['name']);
        Http::assertNothingSent();
    }

    public function test_missing_or_changed_selected_products_are_rejected(): void
    {
        $source = $this->source();
        $source[0]['category'] = 'beauty';
        $this->expectException(\UnexpectedValueException::class);
        (new ImportDemoCatalog())->normalize($source, $this->manual());
    }

    public function test_committed_dataset_has_valid_ownership_ids_money_stock_and_provenance(): void
    {
        $dataset = json_decode(file_get_contents(database_path('seeders/data/catalog-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $dataset['version']);
        self::assertSame('kopecks', $dataset['money_unit']);
        self::assertCount(3, $dataset['stores']);
        self::assertCount(9, $dataset['categories']);
        self::assertCount(48, $dataset['products']);
        $stores = array_column($dataset['stores'], null, 'id');
        $categories = array_column($dataset['categories'], null, 'id');
        $ids = [];
        $slugs = [];
        $sources = [];
        foreach ($dataset['categories'] as $category) {
            self::assertArrayHasKey($category['store_id'], $stores);
        }
        foreach ($dataset['products'] as $product) {
            self::assertTrue(Uuid::isValid($product['id']));
            self::assertNotContains($product['id'], $ids);
            $ids[] = $product['id'];
            $slug = $product['store_id'] . '/' . $product['slug'];
            self::assertNotContains($slug, $slugs);
            $slugs[] = $slug;
            self::assertSame($product['store_id'], $categories[$product['category_id']]['store_id']);
            self::assertIsInt($product['price_per_hour']);
            self::assertGreaterThan(0, $product['price_per_hour']);
            self::assertIsInt($product['deposit_amount']);
            self::assertGreaterThan(0, $product['deposit_amount']);
            self::assertGreaterThan(0, $product['stock']['quantity']);
            self::assertSame(0, $product['stock']['reserved']);
            self::assertSame([], $product['images']);
            self::assertNotEmpty($product['attributes']);
            self::assertStringStartsWith('DEMO ', $product['name']);
            $sources[] = $product['source']['provider'];
        }
        self::assertCount(14, array_filter($sources, fn ($s) => $s === 'dummyjson'));
        self::assertCount(34, array_filter($sources, fn ($s) => $s === 'manual'));
    }

    public function test_seeder_reads_and_validates_local_json_without_http_or_database(): void
    {
        Http::preventStrayRequests();
        DB::shouldReceive('transaction')->never();
        $dataset = (new CatalogSeeder())->readDataset(database_path('seeders/data/catalog-demo.json'));
        self::assertCount(48, $dataset['products']);
        Http::assertNothingSent();
    }

    public function test_seeder_rejects_cross_store_product_before_database_access(): void
    {
        $dataset = json_decode(file_get_contents(database_path('seeders/data/catalog-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        $dataset['products'][0]['store_id'] = $dataset['stores'][2]['id'];
        DB::shouldReceive('transaction')->never();
        $path = tempnam(sys_get_temp_dir(), 'catalog-demo-test-');
        try {
            file_put_contents($path, json_encode($dataset, JSON_THROW_ON_ERROR));
            $this->expectException(\UnexpectedValueException::class);
            (new CatalogSeeder())->readDataset($path);
        } finally {
            unlink($path);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function failures(): iterable
    {
        yield 'unavailable API' => ['unavailable'];
        yield 'invalid JSON' => ['json'];
        yield 'incomplete response' => ['incomplete'];
        yield 'changed selection' => ['selection'];
    }

    #[DataProvider('failures')]
    public function test_failed_import_preserves_previous_file(string $failure): void
    {
        Http::preventStrayRequests();
        $response = match ($failure) {
            'unavailable' => Http::response('', 503),
            'json' => Http::response('{broken', 200),
            'incomplete' => Http::response(['products' => [], 'total' => 1], 200),
            default => Http::response(['products' => [['id' => 1]], 'total' => 1], 200),
        };
        Http::fake(['dummyjson.com/products*' => $response]);
        $path = tempnam(sys_get_temp_dir(), 'catalog-demo-test-');
        try {
            file_put_contents($path, 'previous dataset');
            $this->artisan('catalog:import-demo-data', ['--output' => $path])->assertFailed();
            self::assertSame('previous dataset', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function test_semantically_invalid_manual_data_does_not_replace_previous_dataset(): void
    {
        $manual = $this->manual();
        $source = $this->source();
        $manual['products'][0]['price_per_hour'] = 2147483648;
        Http::preventStrayRequests();
        Http::fake(['dummyjson.com/products*' => Http::response(['products' => $source, 'total' => count($source)])]);
        File::partialMock()->shouldReceive('get')
            ->with(database_path('seeders/data/catalog-demo-manual.json'))
            ->andReturn(json_encode($manual, JSON_THROW_ON_ERROR));
        $path = tempnam(sys_get_temp_dir(), 'catalog-demo-test-');
        try {
            file_put_contents($path, 'previous dataset');
            $this->artisan('catalog:import-demo-data', ['--output' => $path])->assertFailed();
            self::assertSame('previous dataset', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function test_command_writes_normalized_json_using_only_get(): void
    {
        Http::preventStrayRequests();
        $source = $this->source();
        Http::fake(['dummyjson.com/products*' => Http::response(['products' => $source, 'total' => count($source)])]);
        $path = tempnam(sys_get_temp_dir(), 'catalog-demo-test-');
        try {
            $this->artisan('catalog:import-demo-data', ['--output' => $path])->assertSuccessful();
            $dataset = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(48, $dataset['products']);
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://dummyjson.com/products?limit=0');
        } finally {
            unlink($path);
        }
    }
}
