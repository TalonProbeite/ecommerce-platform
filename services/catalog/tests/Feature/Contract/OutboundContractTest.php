<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Domain\Contracts\EventInterface;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\CategoryUpdated;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Messaging\Events\StockChanged;
use App\Messaging\Events\StockReservationFailed;
use App\Messaging\Events\StockReservationFailureReason;
use App\Messaging\Events\StockReserved;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;

final class OutboundContractTest extends PostgresTestCase
{
    private Store $store;
    private Category $category;
    private Product $product;
    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tools']);
        $this->category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 1500,
            'deposit_amount' => 5000,
            'is_active' => true,
        ]);
        $this->stock = Stock::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 10,
            'reserved' => 3,
        ]);
    }

    /**
     * @return iterable<string, array{EventInterface, list<string>, array<string, string>}>
     */
    public static function events(): iterable
    {
        yield 'product.created' => [
            ProductCreated::fromModel(self::productModel()),
            ['product_id', 'store_id', 'category_id', 'name', 'price_per_hour', 'deposit_amount', 'is_active'],
            [
                'product_id' => 'string',
                'store_id' => 'string',
                'category_id' => 'string',
                'name' => 'string',
                'price_per_hour' => 'int',
                'deposit_amount' => 'int',
                'is_active' => 'bool',
            ],
        ];

        yield 'product.updated' => [
            ProductUpdated::fromModel(self::productModel()),
            ['product_id', 'store_id', 'category_id', 'name', 'price_per_hour', 'deposit_amount', 'is_active'],
            [
                'product_id' => 'string',
                'store_id' => 'string',
                'category_id' => 'string',
                'name' => 'string',
                'price_per_hour' => 'int',
                'deposit_amount' => 'int',
                'is_active' => 'bool',
            ],
        ];

        yield 'product.deleted' => [
            ProductDeleted::fromModel(self::productModel()),
            ['product_id', 'store_id'],
            ['product_id' => 'string', 'store_id' => 'string'],
        ];

        yield 'category.created' => [
            CategoryCreated::fromModel(self::categoryModel()),
            ['category_id', 'store_id', 'name', 'slug', 'parent_id'],
            [
                'category_id' => 'string',
                'store_id' => 'string',
                'name' => 'string',
                'slug' => 'string',
                'parent_id' => 'null',
            ],
        ];

        yield 'category.updated' => [
            CategoryUpdated::fromModel(self::categoryModel()),
            ['category_id', 'store_id', 'name', 'slug', 'parent_id'],
            [
                'category_id' => 'string',
                'store_id' => 'string',
                'name' => 'string',
                'slug' => 'string',
                'parent_id' => 'null',
            ],
        ];

        yield 'category.deleted' => [
            CategoryDeleted::fromModel(self::categoryModel()),
            ['category_id', 'store_id'],
            ['category_id' => 'string', 'store_id' => 'string'],
        ];

        yield 'stock.changed' => [
            StockChanged::fromModel(self::stockModel()),
            ['product_id', 'store_id', 'quantity', 'reserved', 'available'],
            [
                'product_id' => 'string',
                'store_id' => 'string',
                'quantity' => 'int',
                'reserved' => 'int',
                'available' => 'int',
            ],
        ];

        yield 'stock.reserved' => [
            new StockReserved('order-1', 'store-1'),
            ['order_id', 'store_id'],
            ['order_id' => 'string', 'store_id' => 'string'],
        ];

        yield 'stock.reservation_failed' => [
            new StockReservationFailed('order-1', 'store-1', StockReservationFailureReason::InsufficientStock),
            ['order_id', 'store_id', 'reason'],
            ['order_id' => 'string', 'store_id' => 'string', 'reason' => 'string'],
        ];
    }

    /**
     * @param list<string> $expectedKeys
     * @param array<string, string> $expectedTypes
     */
    #[Test]
    #[DataProvider('events')]
    public function event_payload_matches_the_documented_shape(EventInterface $event, array $expectedKeys, array $expectedTypes): void
    {
        $payload = $event->toArray();

        self::assertSame($expectedKeys, array_keys($payload));

        foreach ($expectedTypes as $key => $type) {
            self::assertArrayHasKey($key, $payload);
            self::assertSame($type, get_debug_type($payload[$key]), sprintf('Unexpected type for "%s".', $key));
        }
    }

    #[Test]
    public function product_snapshot_uses_the_real_model_values(): void
    {
        $payload = ProductCreated::fromModel($this->product)->toArray();

        self::assertSame($this->product->id, $payload['product_id']);
        self::assertSame($this->store->id, $payload['store_id']);
        self::assertSame($this->category->id, $payload['category_id']);
        self::assertSame('Drill', $payload['name']);
        self::assertSame(1500, $payload['price_per_hour']);
        self::assertSame(5000, $payload['deposit_amount']);
        self::assertTrue($payload['is_active']);
    }

    private static function productModel(): Product
    {
        $product = new Product([
            'store_id' => 'store-1',
            'category_id' => 'category-1',
            'name' => 'Drill',
            'price_per_hour' => 1500,
            'deposit_amount' => 5000,
            'is_active' => true,
        ]);
        $product->id = 'product-1';

        return $product;
    }

    private static function categoryModel(): Category
    {
        $category = new Category([
            'store_id' => 'store-1',
            'name' => 'Tools',
            'slug' => 'tools',
            'parent_id' => null,
        ]);
        $category->id = 'category-1';

        return $category;
    }

    private static function stockModel(): Stock
    {
        $stock = new Stock([
            'product_id' => 'product-1',
            'store_id' => 'store-1',
            'quantity' => 10,
            'reserved' => 3,
        ]);
        $stock->id = 'stock-1';

        return $stock;
    }
}
