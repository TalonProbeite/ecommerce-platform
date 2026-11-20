<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoCatalogDataset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\PostgresTestCase;

class CatalogSeederTest extends PostgresTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'catalog.product_images.disk' => 'seaweedfs',
            'filesystems.disks.seaweedfs.url' => 'https://cdn.test/media/catalog',
        ]);

        Storage::fake('seaweedfs');
        Cache::flush();
    }

    public function test_seeder_creates_two_hundred_products_with_attributes_images_and_working_endpoints(): void
    {
        $dataset = (new DemoCatalogDataset())->make();
        $productCount = count($dataset['products']);

        $this->seed(CatalogSeeder::class);

        self::assertSame(count($dataset['stores']), DB::table('stores')->count());
        self::assertSame(count($dataset['categories']), DB::table('categories')->count());
        self::assertSame($productCount, DB::table('products')->count());
        self::assertSame($productCount, DB::table('stocks')->count());
        self::assertSame($productCount, DB::table('product_images')->where('is_main', true)->count());
        self::assertSame($productCount * 2, DB::table('product_images')->count());

        $productsWithAttributes = DB::table('product_attributes')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('count(*) >= 2')
            ->get()
            ->count();
        self::assertSame($productCount, $productsWithAttributes);

        $mainImage = DB::table('product_images')->where('is_main', true)->first();
        self::assertNotNull($mainImage);
        self::assertSame('image/png', $mainImage->mime_type);
        self::assertGreaterThan(0, $mainImage->size);
        Storage::disk('seaweedfs')->assertExists($mainImage->object_key);

        $list = $this->getJson('/api/v1/products?per_page=5');
        $list->assertOk();
        self::assertIsString($list->json('data.0.main_image_url'));

        $response = $this->getJson('/api/v1/products/' . $mainImage->product_id);
        $response->assertOk();
        $imageUrl = $response->json('data.main_image_url');
        self::assertIsString($imageUrl);
        self::assertStringEndsWith($mainImage->object_key, $imageUrl);

        $images = $this->getJson('/api/v1/products/' . $mainImage->product_id . '/images');
        $images->assertOk()->assertJsonCount(2, 'data');
        $images->assertJsonPath('data.0.is_main', true);
        $images->assertJsonPath('data.0.mime_type', 'image/png');

        $snapshot = $this->counts();
        $this->seed(CatalogSeeder::class);
        self::assertSame($snapshot, $this->counts());
    }

    public function test_seeder_refuses_to_write_demo_data_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => CatalogSeeder::class, '--force' => true])
            ->assertSuccessful();

        self::assertSame(0, DB::table('stores')->count());
        self::assertSame(0, DB::table('categories')->count());
        self::assertSame(0, DB::table('products')->count());
        self::assertSame(0, DB::table('product_images')->count());
    }

    public function test_seeder_restores_soft_deleted_demo_records(): void
    {
        $dataset = (new DemoCatalogDataset())->make();
        $storeId = $dataset['stores'][0]['id'];

        $this->seed(CatalogSeeder::class);

        DB::table('stores')->where('id', $storeId)->update(['deleted_at' => now()]);
        DB::table('categories')->where('store_id', $storeId)->update(['deleted_at' => now()]);
        DB::table('products')->where('store_id', $storeId)->update(['deleted_at' => now()]);

        $this->seed(CatalogSeeder::class);

        self::assertSame(0, DB::table('stores')->whereNotNull('deleted_at')->count());
        self::assertSame(0, DB::table('categories')->whereNotNull('deleted_at')->count());
        self::assertSame(0, DB::table('products')->whereNotNull('deleted_at')->count());
        self::assertSame(count($dataset['products']), DB::table('products')->count());
    }

    public function test_reseeding_restores_a_deleted_main_image_and_promotes_an_existing_demo_row(): void
    {
        $this->seed(CatalogSeeder::class);
        $main = DB::table('product_images')->where('is_main', true)->first();
        DB::table('product_images')->where('id', $main->id)->delete();
        $this->seed(CatalogSeeder::class);
        self::assertTrue((bool) DB::table('product_images')->where('id', $main->id)->value('is_main'));
        DB::table('product_images')->where('id', $main->id)->update(['is_main' => false]);
        $this->seed(CatalogSeeder::class);
        self::assertSame(1, DB::table('product_images')->where('product_id', $main->product_id)->where('is_main', true)->count());
        self::assertTrue((bool) DB::table('product_images')->where('id', $main->id)->value('is_main'));
    }

    /**
     * @return array{stores: int, categories: int, products: int, attributes: int, stocks: int, images: int, main_images: int}
     */
    private function counts(): array
    {
        return [
            'stores' => DB::table('stores')->count(),
            'categories' => DB::table('categories')->count(),
            'products' => DB::table('products')->count(),
            'attributes' => DB::table('product_attributes')->count(),
            'stocks' => DB::table('stocks')->count(),
            'images' => DB::table('product_images')->count(),
            'main_images' => DB::table('product_images')->where('is_main', true)->count(),
        ];
    }
}
