<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database\Seeders\DemoCatalogDataset;
use Database\Seeders\DemoProductImageFactory;
use finfo;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DemoCatalogTest extends TestCase
{
    public function test_dataset_is_deterministic_and_contains_at_least_two_hundred_products(): void
    {
        $dataset = (new DemoCatalogDataset())->make();
        $again = (new DemoCatalogDataset())->make();

        self::assertSame($dataset, $again);
        self::assertSame(2, $dataset['version']);
        self::assertSame('kopecks', $dataset['money_unit']);
        self::assertCount(3, $dataset['stores']);
        self::assertCount(9, $dataset['categories']);
        self::assertGreaterThanOrEqual(200, count($dataset['products']));
    }

    public function test_every_product_has_valid_ownership_money_stock_attributes_and_images(): void
    {
        $dataset = (new DemoCatalogDataset())->make();
        $stores = array_column($dataset['stores'], null, 'id');
        $categories = array_column($dataset['categories'], null, 'id');
        $ids = [];
        $slugs = [];

        foreach ($dataset['products'] as $product) {
            self::assertTrue(Uuid::isValid($product['id']));
            self::assertArrayNotHasKey($product['id'], $ids);
            $ids[$product['id']] = true;

            $slugKey = $product['store_id'] . '/' . $product['slug'];
            self::assertArrayNotHasKey($slugKey, $slugs);
            $slugs[$slugKey] = true;

            self::assertArrayHasKey($product['store_id'], $stores);
            self::assertArrayHasKey($product['category_id'], $categories);
            self::assertSame($product['store_id'], $categories[$product['category_id']]['store_id']);
            self::assertStringStartsWith('DEMO ', $product['name']);
            self::assertStringStartsWith('demo-', $product['slug']);
            self::assertGreaterThan(0, $product['price_per_hour']);
            self::assertGreaterThan(0, $product['deposit_amount']);
            self::assertTrue($product['is_active']);
            self::assertGreaterThan(0, $product['stock']['quantity']);
            self::assertSame(0, $product['stock']['reserved']);

            self::assertGreaterThanOrEqual(2, count($product['attributes']));
            foreach ($product['attributes'] as $attribute) {
                self::assertNotSame('', $attribute['name']);
                self::assertNotSame('', $attribute['value']);
            }

            self::assertNotEmpty($product['images']);
            self::assertTrue($product['images'][0]['is_main']);
            self::assertSame('image/png', $product['images'][0]['mime_type']);
        }
    }

    public function test_image_factory_returns_valid_deterministic_png_files(): void
    {
        $factory = new DemoProductImageFactory();
        $png = $factory->png('photo-video', 1, 0);

        self::assertSame($png, $factory->png('photo-video', 1, 0));
        self::assertNotSame($png, $factory->png('tools', 1, 0));
        self::assertNotSame($png, $factory->png('photo-video', 2, 0));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $header = unpack('Nwidth/Nheight', substr($png, 16, 8));
        self::assertSame(480, $header['width']);
        self::assertSame(360, $header['height']);
        self::assertLessThan(5 * 1024 * 1024, strlen($png));

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($png);
        self::assertSame('image/png', $mimeType);
    }
}
