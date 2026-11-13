<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Cache\CategoryCache;
use App\Infrastructure\Cache\ProductCache;
use App\Infrastructure\Storage\S3BucketGuard;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use UnexpectedValueException;

/**
 * @phpstan-import-type Dataset from DemoCatalogDataset
 * @phpstan-import-type ProductRow from DemoCatalogDataset
 */
class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $dataset = (new DemoCatalogDataset())->make();
        $disk = (string) config('catalog.product_images.disk');
        $storage = Storage::disk($disk);

        $this->ensureImageBucket($disk);

        $images = new DemoProductImageFactory();

        // Query builder deliberately bypasses model events and the publishing repositories.
        DB::transaction(function () use ($dataset, $storage, $images): void {
            $stores = [];

            foreach ($dataset['stores'] as $store) {
                $existing = DB::table('stores')->where('id', $store['id'])->first();

                $row = $store;
                $row['deleted_at'] = null;

                $this->write('stores', $row, $existing !== null);
                $stores[$store['id']] = true;
            }

            $categories = [];

            foreach ($dataset['categories'] as $category) {
                if (! isset($stores[$category['store_id']])) {
                    continue;
                }

                $existing = DB::table('categories')->where('id', $category['id'])->first();

                if ($existing !== null && $existing->store_id !== $category['store_id']) {
                    throw new UnexpectedValueException('DEMO category ownership changed: ' . $category['id']);
                }

                $row = $category;
                $row['deleted_at'] = null;

                $this->write('categories', $row, $existing !== null);
                $categories[$category['id']] = true;
            }

            foreach ($dataset['products'] as $product) {
                if (! isset($stores[$product['store_id']], $categories[$product['category_id']])) {
                    continue;
                }

                $existing = DB::table('products')->where('id', $product['id'])->first();

                if ($existing !== null && ($existing->store_id !== $product['store_id'] || $existing->category_id !== $product['category_id'])) {
                    throw new UnexpectedValueException('DEMO product ownership changed: ' . $product['id']);
                }

                $row = $product;
                unset($row['attributes'], $row['stock'], $row['images'], $row['category_key'], $row['position']);
                $row['deleted_at'] = null;

                $this->write('products', $row, $existing !== null);

                $this->syncStock($product);
                $this->syncAttributes($product);
                $this->syncImages($product, $storage, $images);
            }

            DB::afterCommit(function () use ($dataset): void {
                rescue(function () use ($dataset): void {
                    $products = app(ProductCache::class);
                    $products->invalidateLists();

                    foreach ($dataset['products'] as $product) {
                        $products->invalidateCard($product['id']);
                    }

                    $categories = app(CategoryCache::class);

                    foreach ($dataset['stores'] as $store) {
                        $categories->invalidateStore($store['id']);
                    }
                }, report: true);
            });
        });
    }

    private function ensureImageBucket(string $disk): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (config("filesystems.disks.{$disk}.driver") !== 's3') {
            return;
        }

        app(S3BucketGuard::class)->ensure($disk);
    }

    /**
     * @param ProductRow $product
     */
    private function syncStock(array $product): void
    {
        $existing = DB::table('stocks')->where('product_id', $product['id'])->first();

        if ($existing !== null) {
            return;
        }

        $this->write('stocks', [
            'id' => Uuid::uuid5($product['id'], 'demo-stock')->toString(),
            'product_id' => $product['id'],
            'store_id' => $product['store_id'],
            'quantity' => $product['stock']['quantity'],
            'reserved' => 0,
        ], false);
    }

    /**
     * @param ProductRow $product
     */
    private function syncAttributes(array $product): void
    {
        $rows = [];

        foreach ($product['attributes'] as $attribute) {
            $rows[] = [
                'id' => Uuid::uuid5($product['id'], 'demo-attribute/' . $attribute['name'])->toString(),
                'product_id' => $product['id'],
                'name' => $attribute['name'],
                'value' => $attribute['value'],
            ];
        }

        $existing = DB::table('product_attributes')
            ->whereIn('id', array_column($rows, 'id'))
            ->pluck('id')
            ->all();
        $existing = array_fill_keys($existing, true);

        foreach ($rows as $row) {
            $this->write('product_attributes', $row, isset($existing[$row['id']]));
        }
    }

    /**
     * @param ProductRow $product
     */
    private function syncImages(array $product, Filesystem $storage, DemoProductImageFactory $images): void
    {
        $rows = [];

        foreach ($product['images'] as $image) {
            $imageId = Uuid::uuid5($product['id'], 'demo-image/' . $image['index'])->toString();
            $rows[] = [
                'id' => $imageId,
                'product_id' => $product['id'],
                'object_key' => sprintf(
                    'stores/%s/products/%s/%s.png',
                    $product['store_id'],
                    $product['id'],
                    $imageId,
                ),
                'mime_type' => $image['mime_type'],
                'is_main' => $image['is_main'],
                'index' => $image['index'],
            ];
        }

        $existing = DB::table('product_images')
            ->whereIn('id', array_column($rows, 'id'))
            ->get()
            ->keyBy('id');
        $hasMain = DB::table('product_images')
            ->where('product_id', $product['id'])
            ->where('is_main', true)
            ->exists();

        foreach ($rows as $row) {
            $current = $existing->get($row['id']);
            $objectKey = $row['object_key'];

            if ($current === null || ! $storage->exists($objectKey)) {
                $bytes = $images->png($product['category_key'], $product['position'], $row['index']);

                if ($storage->put($objectKey, $bytes, ['ContentType' => $row['mime_type']]) !== true) {
                    throw new RuntimeException('Unable to store DEMO product image: ' . $objectKey);
                }

                $size = strlen($bytes);
            } else {
                $size = (int) $current->size;
            }

            $isMain = (bool) ($current->is_main ?? false) || (! $hasMain && $row['is_main']);

            if ($isMain) {
                $hasMain = true;
            }

            $this->write('product_images', [
                'id' => $row['id'],
                'product_id' => $row['product_id'],
                'object_key' => $objectKey,
                'mime_type' => $row['mime_type'],
                'size' => $size,
                'is_main' => $isMain,
            ], $current !== null);
        }
    }

    /** @param array<string, mixed> $row */
    private function write(string $table, array $row, bool $exists): void
    {
        unset($row['index']);

        $row['updated_at'] = now();

        if (! $exists) {
            $row['created_at'] = $row['updated_at'];
        }

        DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
    }
}
