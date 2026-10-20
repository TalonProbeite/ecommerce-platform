<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;

/**
 * @phpstan-import-type Dataset from \App\Console\Commands\ImportDemoCatalog
 */
class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $dataset = $this->readDataset(database_path('seeders/data/catalog-demo.json'));

        // Query builder deliberately bypasses model events and the publishing repositories.
        DB::transaction(function () use ($dataset): void {
            $stores = [];
            foreach ($dataset['stores'] as $store) {
                $existing = DB::table('stores')->where('id', $store['id'])->first();
                if ($existing !== null && $existing->deleted_at !== null) {
                    continue;
                }
                $this->write('stores', $store, $existing !== null);
                $stores[$store['id']] = true;
            }
            $categories = [];
            foreach ($dataset['categories'] as $category) {
                if (! isset($stores[$category['store_id']])) {
                    continue;
                }
                $existing = DB::table('categories')->where('id', $category['id'])->first();
                if ($existing !== null && $existing->deleted_at !== null) {
                    continue;
                }
                if ($existing !== null && $existing->store_id !== $category['store_id']) {
                    throw new UnexpectedValueException('DEMO category ownership changed: ' . $category['id']);
                }
                $this->write('categories', $category, $existing !== null);
                $categories[$category['id']] = true;
            }
            foreach ($dataset['products'] as $product) {
                if (! isset($stores[$product['store_id']], $categories[$product['category_id']])) {
                    continue;
                }
                $existing = DB::table('products')->where('id', $product['id'])->first();
                if ($existing !== null && $existing->deleted_at !== null) {
                    continue;
                }
                if ($existing !== null && ($existing->store_id !== $product['store_id'] || $existing->category_id !== $product['category_id'])) {
                    throw new UnexpectedValueException('DEMO product ownership changed: ' . $product['id']);
                }
                $row = $product;
                unset($row['attributes'], $row['stock'], $row['images'], $row['source']);
                $this->write('products', $row, $existing !== null);
                if ($existing !== null) {
                    // Existing inventory and physically deleted attributes are never re-created.
                    continue;
                }
                foreach ($product['attributes'] as $attribute) {
                    $this->write('product_attributes', $attribute + ['product_id' => $product['id']], false);
                }
                $this->write('stocks', [
                    'id' => Uuid::uuid5($product['id'], 'demo-stock')->toString(),
                    'product_id' => $product['id'], 'store_id' => $product['store_id'],
                    'quantity' => $product['stock']['quantity'], 'reserved' => 0,
                ], false);
            }
        });
    }

    /**
     * Validate the complete local file before opening a database transaction.
     *
     * @return Dataset
     */
    public function readDataset(string $path): array
    {
        $dataset = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($dataset)) {
            throw new UnexpectedValueException('DEMO dataset must be a JSON object.');
        }
        return $this->validateDataset($dataset);
    }

    /**
     * Shared with the importer so an invalid refresh cannot replace the offline file.
     * This method performs no database, HTTP or filesystem operations.
     *
     * @param array<string, mixed> $dataset
     * @return Dataset
     */
    public function validateDataset(array $dataset): array
    {
        $uuid = ['required', 'uuid', 'distinct'];
        $text = ['required', 'string', 'max:255'];
        $demoName = [...$text, 'starts_with:DEMO '];
        $slug = [...$text, 'starts_with:demo-'];
        $money = ['required', 'integer', 'min:0', 'max:2147483647'];
        Validator::make($dataset, [
            'version' => ['required', 'integer', 'in:1'],
            'money_unit' => ['required', 'in:kopecks'],
            'pricing' => ['required', 'string'], 'image_policy' => ['required', 'string'],
            'stores' => ['required', 'array', 'list', 'min:2', 'max:3'],
            'stores.*.id' => $uuid, 'stores.*.name' => $demoName,
            'stores.*.description' => ['required', 'string', 'starts_with:DEMO'],
            'stores.*.location' => $text, 'stores.*.active' => ['required', 'boolean'],
            'categories' => ['required', 'array', 'list', 'min:7'],
            'categories.*.id' => $uuid, 'categories.*.store_id' => ['required', 'uuid'],
            'categories.*.name' => $demoName, 'categories.*.slug' => $slug,
            'categories.*.parent_id' => ['present', 'prohibited'],
            'products' => ['required', 'array', 'list', 'min:40', 'max:60'],
            'products.*.id' => $uuid, 'products.*.store_id' => ['required', 'uuid'],
            'products.*.category_id' => ['required', 'uuid'], 'products.*.name' => $demoName,
            'products.*.slug' => $slug, 'products.*.description' => ['required', 'string', 'starts_with:DEMO'],
            'products.*.price_per_hour' => $money, 'products.*.deposit_amount' => $money,
            'products.*.rating' => ['required', 'in:0.00'], 'products.*.reviews_count' => ['required', 'integer', 'in:0'],
            'products.*.is_active' => ['required', 'boolean'],
            'products.*.stock.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'products.*.stock.reserved' => ['required', 'integer', 'in:0'],
            'products.*.images' => ['present', 'array', 'size:0'],
            'products.*.attributes' => ['required', 'array', 'list', 'min:1'],
            'products.*.attributes.*.id' => $uuid, 'products.*.attributes.*.name' => $text,
            'products.*.attributes.*.value' => $text,
            'products.*.source.provider' => ['required', 'in:dummyjson,manual'],
            'products.*.source.id' => ['required'], 'products.*.source.image_urls' => ['present', 'array', 'list'],
            'products.*.source.image_urls.*' => ['required', 'url:https'],
        ])->validate();
        /** @var Dataset $dataset */
        $stores = array_column($dataset['stores'], null, 'id');
        $categories = array_column($dataset['categories'], null, 'id');
        $slugs = [];
        foreach ($dataset['categories'] as $category) {
            $key = 'category/' . $category['store_id'] . '/' . $category['slug'];
            if (! isset($stores[$category['store_id']]) || isset($slugs[$key])) {
                throw new UnexpectedValueException('Invalid DEMO category ownership or duplicate slug.');
            }
            $slugs[$key] = true;
        }
        foreach ($dataset['products'] as $product) {
            $key = 'product/' . $product['store_id'] . '/' . $product['slug'];
            if (! isset($categories[$product['category_id']])
                || $categories[$product['category_id']]['store_id'] !== $product['store_id'] || isset($slugs[$key])) {
                throw new UnexpectedValueException('Invalid DEMO product ownership or duplicate slug.');
            }
            $slugs[$key] = true;
        }

        return $dataset;
    }

    /** @param array<string, mixed> $row */
    private function write(string $table, array $row, bool $exists): void
    {
        $row['updated_at'] = now();
        if (! $exists) {
            $row['created_at'] = $row['updated_at'];
        }
        DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
    }
}
