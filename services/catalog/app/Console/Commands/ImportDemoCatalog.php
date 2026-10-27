<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;

/**
 * @phpstan-type Attribute array{id: string, name: string, value: string}
 * @phpstan-type StoreRow array{id: string, name: string, description: string, location: string, active: bool}
 * @phpstan-type CategoryRow array{id: string, store_id: string, name: string, slug: string, parent_id: null}
 * @phpstan-type ProductRow array{id: string, store_id: string, category_id: string, name: string, slug: string, description: string, price_per_hour: int, deposit_amount: int, rating: string, reviews_count: int, is_active: bool, attributes: list<Attribute>, stock: array{quantity: int, reserved: int}, images: array{}, source: array{provider: string, id: int|string, image_urls: list<string>}}
 * @phpstan-type Dataset array{version: int, money_unit: string, pricing: string, image_policy: string, stores: list<StoreRow>, categories: list<CategoryRow>, products: list<ProductRow>}
 */
class ImportDemoCatalog extends Command
{
    protected $signature = 'catalog:import-demo-data {--output= : Destination JSON; defaults to the committed seeder dataset}';

    protected $description = 'Import curated DummyJSON products and local DEMO equipment into an offline dataset';

    public function handle(): int
    {
        try {
            $response = Http::acceptJson()->connectTimeout(10)->timeout(45)
                ->get('https://dummyjson.com/products', ['limit' => 0])->throw();
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ! isset($payload['products'], $payload['total'])
                || ! is_array($payload['products']) || ! array_is_list($payload['products'])
                || $payload['total'] !== count($payload['products']) || $payload['total'] === 0) {
                throw new UnexpectedValueException('DummyJSON returned an incomplete products response.');
            }
            $manual = json_decode(File::get(database_path('seeders/data/catalog-demo-manual.json')), true, 512, JSON_THROW_ON_ERROR);
            $dataset = (new CatalogSeeder())->validateDataset($this->normalize($payload['products'], $manual));
            $json = json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $output = $this->option('output');
            $path = is_string($output) && $output !== ''
                ? $output
                : database_path('seeders/data/catalog-demo.json');
            if (! is_dir(dirname($path))) {
                throw new UnexpectedValueException('The dataset destination directory must exist.');
            }
            // Laravel writes a temporary file beside the target, then renames it atomically.
            File::replace($path, $json, 0644);
            $this->info('Saved ' . count($dataset['products']) . ' DEMO products to ' . $path);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Demo import failed; previous dataset preserved: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * IDs, titles and source categories form an explicit allowlist in the manual input.
     * Price, stock, ratings and unverified technical descriptions from the mock API are ignored.
     *
     * @param list<array<string, mixed>> $products
     * @param array{stores: list<array{key: string, name: string, location: string}>, categories: list<array{store: string, key: string, name: string, price_per_hour: int, deposit_amount: int}>, imports: list<array{id: int, title: string, source_category: string, store: string, category: string, kind: string}>, products: list<array{key: string, store: string, category: string, name: string, price_per_hour: int, deposit_amount: int, quantity: int, attributes: list<array{name: string, value: string}>}>} $manual
     * @return Dataset
     */
    public function normalize(array $products, array $manual): array
    {
        $dataset = [
            'version' => 1,
            'money_unit' => 'kopecks',
            'pricing' => 'Fixed DEMO hourly rates and deposits; unrelated to DummyJSON sale prices. Stock is synthetic.',
            'image_policy' => 'Source URLs are metadata only. Image reuse rights have not been confirmed; no product_images or storage uploads.',
            'stores' => [], 'categories' => [], 'products' => [],
        ];
        $stores = [];
        foreach ($manual['stores'] as $store) {
            $id = $this->id('store/' . $store['key']);
            $stores[$store['key']] = $id;
            $dataset['stores'][] = ['id' => $id, 'name' => $store['name'],
                'description' => 'DEMO seed data — fictional rental store.', 'location' => $store['location'], 'active' => true];
        }
        $categories = [];
        foreach ($manual['categories'] as $category) {
            $key = $category['store'] . '/' . $category['key'];
            $id = $this->id('category/' . $key);
            $categories[$key] = $category + ['id' => $id];
            $dataset['categories'][] = ['id' => $id, 'store_id' => $stores[$category['store']],
                'name' => 'DEMO ' . $category['name'], 'slug' => 'demo-' . $category['key'], 'parent_id' => null];
        }
        $source = [];
        foreach ($products as $product) {
            if (! isset($product['id']) || ! is_int($product['id']) || isset($source[$product['id']])) {
                throw new UnexpectedValueException('DummyJSON products must have unique integer IDs.');
            }
            $source[$product['id']] = $product;
        }
        foreach ($manual['imports'] as $selection) {
            $product = $source[$selection['id']] ?? [];
            if (($product['title'] ?? null) !== $selection['title'] || ($product['category'] ?? null) !== $selection['source_category']) {
                throw new UnexpectedValueException('Selected DummyJSON product changed or missing: ' . $selection['id']);
            }
            $category = $categories[$selection['store'] . '/' . $selection['category']];
            $key = 'dummyjson-' . $selection['id'];
            $row = $this->product(
                $key,
                $selection['title'],
                $stores[$selection['store']],
                $category['id'],
                $category['price_per_hour'],
                $category['deposit_amount'],
                3
            );
            $row['attributes'][] = $this->attribute($key, 'Тип', $selection['kind']);
            if (isset($product['brand']) && is_string($product['brand']) && $product['brand'] !== '') {
                $row['attributes'][] = $this->attribute($key, 'Бренд (mock-источник)', mb_substr($product['brand'], 0, 255));
            }
            $urls = [];
            if (! isset($product['images']) || ! is_array($product['images'])) {
                throw new UnexpectedValueException('Missing image metadata for product ' . $selection['id']);
            }
            foreach ($product['images'] as $url) {
                if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                    || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'cdn.dummyjson.com') {
                    throw new UnexpectedValueException('Invalid source image URL for product ' . $selection['id']);
                }
                $urls[] = $url;
            }
            $urls = array_values(array_unique($urls));
            sort($urls, SORT_STRING);
            $row['source'] = ['provider' => 'dummyjson', 'id' => $selection['id'], 'image_urls' => $urls];
            $dataset['products'][] = $row;
        }
        foreach ($manual['products'] as $product) {
            $key = 'manual-' . $product['key'];
            $category = $categories[$product['store'] . '/' . $product['category']];
            $row = $this->product(
                $key,
                $product['name'],
                $stores[$product['store']],
                $category['id'],
                $product['price_per_hour'],
                $product['deposit_amount'],
                $product['quantity']
            );
            foreach ($product['attributes'] as $attribute) {
                $row['attributes'][] = $this->attribute($key, $attribute['name'], $attribute['value']);
            }
            $dataset['products'][] = $row;
        }
        usort($dataset['products'], fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $dataset;
    }

    /** @return ProductRow */
    private function product(string $key, string $name, string $store, string $category, int $price, int $deposit, int $quantity): array
    {
        if ($price <= 0 || $deposit < 0 || $quantity <= 0) {
            throw new UnexpectedValueException('Invalid DEMO rental terms: ' . $key);
        }

        return ['id' => $this->id('product/' . $key), 'store_id' => $store, 'category_id' => $category,
            'name' => 'DEMO ' . $name, 'slug' => 'demo-' . $key,
            'description' => str_starts_with($key, 'manual-')
                ? 'DEMO: вымышленный товар и демонстрационные характеристики; не спецификация реальной модели.'
                : 'DEMO: название и бренд из DummyJSON (mock API), характеристики реальной модели не проверены. Условия аренды демонстрационные.',
            'price_per_hour' => $price, 'deposit_amount' => $deposit, 'rating' => '0.00', 'reviews_count' => 0,
            'is_active' => true, 'attributes' => [], 'stock' => ['quantity' => $quantity, 'reserved' => 0], 'images' => [],
            'source' => ['provider' => 'manual', 'id' => $key, 'image_urls' => []]];
    }

    /** @return Attribute */
    private function attribute(string $product, string $name, string $value): array
    {
        return ['id' => $this->id('attribute/' . $product . '/' . $name), 'name' => $name, 'value' => $value];
    }

    private function id(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://rentify.example/demo/catalog/v1/' . $key)->toString();
    }
}
