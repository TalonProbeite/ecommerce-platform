<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class CatalogSchemaTest extends PostgresTestCase
{
    private string $storeId;
    private string $categoryId;
    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeId = (string) Uuid::uuid7();
        $this->categoryId = (string) Uuid::uuid7();
        $this->productId = (string) Uuid::uuid7();
        DB::table('stores')->insert(['id' => $this->storeId, 'name' => 'Rental company']);
        DB::table('categories')->insert([
            'id' => $this->categoryId, 'name' => 'Equipment', 'slug' => 'equipment',
            'store_id' => $this->storeId,
        ]);
        DB::table('products')->insert([
            'id' => $this->productId, 'name' => 'Drill', 'slug' => 'drill', 'price_per_hour' => 12345,
            'store_id' => $this->storeId, 'category_id' => $this->categoryId,
        ]);
        DB::table('stocks')->insert([
            'id' => (string) Uuid::uuid7(), 'product_id' => $this->productId,
            'store_id' => $this->storeId, 'quantity' => 10, 'reserved' => 2,
        ]);
        DB::table('product_attributes')->insert([
            'id' => (string) Uuid::uuid7(), 'product_id' => $this->productId,
            'name' => 'Power', 'value' => '800 W',
        ]);
        DB::table('product_images')->insert([
            'id' => (string) Uuid::uuid7(), 'product_id' => $this->productId,
            'path' => 'equipment/drill.jpg', 'is_main' => true,
        ]);
    }

    private function reservation(): array
    {
        return [
            'id' => (string) Uuid::uuid7(), 'order_id' => (string) Uuid::uuid7(),
            'product_id' => $this->productId, 'store_id' => $this->storeId,
            'quantity' => 2, 'status' => 'reserved',
            'rental_start_at' => '2026-10-13 10:00:00+02',
            'rental_end_at' => '2026-10-13 12:00:00+02',
        ];
    }

    public function test_schema_has_exactly_seven_table_migrations(): void
    {
        $this->assertCount(7, glob(database_path('migrations/*.php')));
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', $this->testSchema)
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')->pluck('table_name')->all();
        $this->assertSame([
            'categories', 'product_attributes', 'product_images', 'products',
            'stock_reservations', 'stocks', 'stores',
        ], $tables);
    }

    public function test_rental_fields_have_correct_defaults_and_support_fractional_ratings(): void
    {
        $product = Product::findOrFail($this->productId);
        $this->assertSame(12345, $product->price_per_hour);
        $this->assertSame(0, $product->deposit_amount);
        $this->assertSame('0.00', $product->rating);
        $this->assertSame(0, $product->reviews_count);
        $this->assertFalse(Schema::hasColumn('products', 'price'));
        $this->assertNull(Store::findOrFail($this->storeId)->description);
        $this->assertNull(Store::findOrFail($this->storeId)->location);
        $this->assertNull(Category::findOrFail($this->categoryId)->parent_id);
        $this->assertSame(8, $product->stock->available);
        $this->assertSame('800 W', $product->attributes()->firstOrFail()->value);
        $this->assertSame('equipment/drill.jpg', $product->images()->firstOrFail()->path);

        $product->update(['rating' => '4.25', 'deposit_amount' => 10000, 'reviews_count' => 7]);
        $this->assertSame('4.25', $product->fresh()->rating);
        $this->assertSame(10000, $product->fresh()->deposit_amount);
        Store::findOrFail($this->storeId)->update(['description' => 'Tool rental', 'location' => 'Warsaw']);
        $this->assertSame('Warsaw', Store::findOrFail($this->storeId)->location);
    }

    public static function invalidProductNumbers(): array
    {
        return [
            'negative hourly price' => ['price_per_hour', -1],
            'negative deposit' => ['deposit_amount', -1],
            'negative rating' => ['rating', '-0.01'],
            'rating above five' => ['rating', '5.01'],
            'negative reviews count' => ['reviews_count', -1],
        ];
    }

    #[DataProvider('invalidProductNumbers')]
    public function test_product_number_constraints(string $column, int|string $value): void
    {
        $this->assertRejected(
            fn () => DB::table('products')->where('id', $this->productId)->update([$column => $value]),
            '23514',
        );
    }

    public static function invalidReservationValues(): array
    {
        return [
            'zero quantity' => [['quantity' => 0]],
            'negative quantity' => [['quantity' => -1]],
            'unknown status' => [['status' => 'pending']],
            'obsolete status' => [['status' => 'completed']],
        ];
    }

    #[DataProvider('invalidReservationValues')]
    public function test_reservation_constraints(array $values): void
    {
        $this->assertRejected(
            fn () => DB::table('stock_reservations')->insert(array_replace($this->reservation(), $values)),
            '23514',
        );
    }

    public function test_unique_slug_includes_soft_deleted_products_and_is_scoped_to_store(): void
    {
        Product::findOrFail($this->productId)->delete();
        $this->assertRejected(fn () => Product::create([
            'name' => 'Duplicate drill', 'slug' => 'drill', 'price_per_hour' => 0,
            'store_id' => $this->storeId, 'category_id' => $this->categoryId,
        ]), '23505');

        $store = Store::create(['name' => 'Other company']);
        $category = Category::create(['name' => 'Equipment', 'slug' => 'equipment', 'store_id' => $store->id]);
        $product = Product::create([
            'name' => 'Drill', 'slug' => 'drill', 'price_per_hour' => 0,
            'store_id' => $store->id, 'category_id' => $category->id,
        ]);
        $this->assertTrue($product->exists);
    }

    public function test_only_one_main_image_per_product(): void
    {
        $this->assertRejected(fn () => ProductImage::create([
            'product_id' => $this->productId, 'path' => 'other.jpg', 'is_main' => true,
        ]), '23505');
        ProductImage::create(['product_id' => $this->productId, 'path' => 'side.jpg']);
        ProductImage::create(['product_id' => $this->productId, 'path' => 'back.jpg']);
        $this->assertSame(3, ProductImage::count());
    }

    public function test_reservations_accept_external_order_ids_and_prevent_duplicate_order_lines(): void
    {
        $values = $this->reservation();
        foreach (['reserved', 'issued', 'released', 'returned'] as $status) {
            $reservation = StockReservation::create(array_replace($values, [
                'order_id' => (string) Uuid::uuid7(), 'status' => $status,
            ]));
            $this->assertSame(2, $reservation->quantity);
            $this->assertSame('2026-10-13 08:00:00', $reservation->rental_start_at->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-13 08:00:00', $reservation->fresh()->rental_start_at->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-13 10:00:00', $reservation->fresh()->rental_end_at->utc()->format('Y-m-d H:i:s'));
            $this->assertSame(7, Uuid::fromString($reservation->id)->getVersion());
            $this->assertRejected(fn () => StockReservation::create(array_replace($values, [
                'order_id' => $reservation->order_id, 'status' => $status,
            ])), '23505');
        }
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertSame(4, StockReservation::count());
    }

    public function test_reservation_dates_are_optional_information_and_do_not_release_stock(): void
    {
        $reservation = StockReservation::create(array_replace($this->reservation(), [
            'rental_start_at' => null, 'rental_end_at' => null,
        ]));
        $this->assertNull($reservation->fresh()->issued_at);
        $this->assertNull($reservation->fresh()->returned_at);
        $reservation->update([
            'status' => 'issued',
            'issued_at' => '2026-10-13 10:00:00+02',
            'rental_start_at' => '2026-10-13 12:00:00+02',
            'rental_end_at' => '2026-10-13 09:00:00+02',
        ]);
        $this->assertSame('issued', $reservation->fresh()->status);
        $this->assertSame('2026-10-13 08:00:00', $reservation->fresh()->issued_at->utc()->format('Y-m-d H:i:s'));
        $reservation->update(['status' => 'returned', 'returned_at' => '2026-10-14 10:00:00+02']);
        $this->assertSame('2026-10-14 08:00:00', $reservation->fresh()->returned_at->utc()->format('Y-m-d H:i:s'));
        $stock = Stock::where('product_id', $this->productId)->firstOrFail();
        $this->assertSame(10, $stock->quantity);
        $this->assertSame(2, $stock->reserved);
        $this->assertSame(8, $stock->available);
    }

    public function test_related_entities_cannot_belong_to_different_stores(): void
    {
        $store = Store::create(['name' => 'Other company']);
        $this->assertRejected(fn () => Category::create([
            'name' => 'Child', 'slug' => 'child', 'store_id' => $store->id, 'parent_id' => $this->categoryId,
        ]), '23503');
        $this->assertRejected(fn () => Product::create([
            'name' => 'Foreign category', 'slug' => 'foreign', 'price_per_hour' => 0,
            'store_id' => $store->id, 'category_id' => $this->categoryId,
        ]), '23503');
        $this->assertRejected(
            fn () => DB::table('stocks')->where('product_id', $this->productId)->update(['store_id' => $store->id]),
            '23503',
        );
        $this->assertRejected(fn () => StockReservation::create(
            array_replace($this->reservation(), ['store_id' => $store->id]),
        ), '23503');
        $this->assertRejected(
            fn () => DB::table('products')->where('id', $this->productId)->update(['store_id' => $store->id]),
            '23503',
        );
    }

    public function test_relationships_load_lazily_and_eagerly_for_multiple_products(): void
    {
        $parent = Category::findOrFail($this->categoryId);
        $child = Category::create([
            'name' => 'Tools', 'slug' => 'tools', 'store_id' => $this->storeId, 'parent_id' => $parent->id,
        ]);
        $second = Product::create([
            'name' => 'Saw', 'slug' => 'saw', 'price_per_hour' => 250,
            'store_id' => $this->storeId, 'category_id' => $child->id,
        ]);
        Stock::create(['product_id' => $second->id, 'store_id' => $this->storeId, 'quantity' => 4]);
        foreach ([$this->productId, $second->id] as $productId) {
            StockReservation::create(array_replace($this->reservation(), ['product_id' => $productId]));
        }

        $store = Store::with(['products.stock', 'categories', 'stocks.stockReservations'])->findOrFail($this->storeId);
        $this->assertCount(2, $store->products);
        $this->assertCount(2, $store->categories);
        $this->assertCount(2, $store->stocks);
        foreach (Stock::with(['product', 'store', 'stockReservations'])->get() as $stock) {
            $this->assertTrue($stock->store->is($store));
            $this->assertSame($stock->product_id, $stock->product->id);
            $this->assertCount(1, $stock->stockReservations);
            $this->assertSame($stock->product_id, $stock->stockReservations->first()->product_id);
            $this->assertCount(1, $stock->fresh()->stockReservations);
        }
        foreach (Product::with(['store', 'category', 'stock', 'attributes', 'images', 'stockReservations'])->get() as $product) {
            $this->assertTrue($product->store->is($store));
            $this->assertSame($product->id, $product->stock->product_id);
            $this->assertCount(1, $product->stockReservations);
        }
        foreach (StockReservation::with(['product', 'store'])->get() as $reservation) {
            $this->assertSame($reservation->product_id, $reservation->product->id);
            $this->assertTrue($reservation->store->is($store));
        }
        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains('id', $child->id));
        $this->assertTrue($child->products->first()->is($second));
        $this->assertTrue($child->store->is($store));
        $this->assertSame($this->productId, ProductAttribute::firstOrFail()->product->id);
        $this->assertSame($this->productId, ProductImage::firstOrFail()->product->id);
    }

    public function test_hard_deletes_preserve_referenced_data_and_parent_deletion_only_nulls_parent_id(): void
    {
        StockReservation::create($this->reservation());
        $this->assertRejected(fn () => Product::findOrFail($this->productId)->forceDelete(), '23503');
        $this->assertRejected(fn () => Store::findOrFail($this->storeId)->forceDelete(), '23503');
        $this->assertRejected(fn () => Category::findOrFail($this->categoryId)->forceDelete(), '23503');
        $this->assertSame(1, StockReservation::count());

        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'store_id' => $this->storeId]);
        $child = Category::create([
            'name' => 'Child', 'slug' => 'child', 'store_id' => $this->storeId, 'parent_id' => $parent->id,
        ]);
        $parent->forceDelete();
        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame($this->storeId, $child->fresh()->store_id);
    }

    public static function invalidStockQuantities(): array
    {
        return [
            'negative quantity' => [['quantity' => -1]],
            'negative reserved quantity' => [['reserved' => -1]],
            'reserved exceeds quantity' => [['reserved' => 11]],
        ];
    }

    #[DataProvider('invalidStockQuantities')]
    public function test_stock_quantity_constraints_remain_enforced(array $values): void
    {
        $this->assertRejected(
            fn () => DB::table('stocks')->where('product_id', $this->productId)->update($values),
            '23514',
        );
    }

    public function test_schema_uses_uuid_integer_numeric_and_timezone_aware_rental_columns(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', $this->testSchema)
            ->whereIn('table_name', ['products', 'stock_reservations'])
            ->get()->keyBy(fn ($column) => $column->table_name . '.' . $column->column_name);

        $this->assertSame('integer', $columns['products.price_per_hour']->data_type);
        $this->assertSame('integer', $columns['products.deposit_amount']->data_type);
        $this->assertSame('integer', $columns['products.reviews_count']->data_type);
        $this->assertSame('numeric', $columns['products.rating']->data_type);
        $this->assertSame(3, $columns['products.rating']->numeric_precision);
        $this->assertSame(2, $columns['products.rating']->numeric_scale);
        foreach (['id', 'order_id', 'product_id', 'store_id'] as $column) {
            $this->assertSame('uuid', $columns['stock_reservations.' . $column]->data_type);
            $this->assertSame('NO', $columns['stock_reservations.' . $column]->is_nullable);
        }
        foreach (['rental_start_at', 'rental_end_at', 'issued_at', 'returned_at'] as $column) {
            $this->assertSame('timestamp with time zone', $columns['stock_reservations.' . $column]->data_type);
            $this->assertSame('YES', $columns['stock_reservations.' . $column]->is_nullable);
        }
        $this->assertSame('timestamp without time zone', $columns['stock_reservations.created_at']->data_type);
        $this->assertSame('timestamp without time zone', $columns['stock_reservations.updated_at']->data_type);

        $indexes = Schema::getIndexes('stock_reservations');
        $this->assertNotEmpty(array_filter($indexes, fn ($index) => $index['columns'] === ['order_id', 'product_id'] && $index['unique']));
        $this->assertNotEmpty(array_filter($indexes, fn ($index) => $index['columns'] === ['product_id']));
        $this->assertNotEmpty(array_filter($indexes, fn ($index) => $index['columns'] === ['store_id', 'status']));
        $this->assertEmpty(array_filter($indexes, fn ($index) => $index['columns'] === ['rental_start_at', 'rental_end_at']));
    }

    public function test_all_migrations_can_roll_back_and_recreate_the_schema(): void
    {
        $paths = glob(database_path('migrations/*.php'));
        foreach (array_reverse($paths) as $path) {
            DB::transaction(fn () => (require $path)->down());
        }

        $this->assertSame(0, DB::table('information_schema.tables')
            ->where('table_schema', $this->testSchema)->where('table_type', 'BASE TABLE')->count());
        $this->createSchema();
        $store = Store::create(['name' => 'Rental company']);
        $root = Category::create(['name' => 'Root', 'slug' => 'root', 'store_id' => $store->id]);
        $product = Product::create([
            'name' => 'Drill', 'slug' => 'drill', 'price_per_hour' => 12345,
            'store_id' => $store->id, 'category_id' => $root->id,
        ]);
        $this->assertNull($root->parent_id);
        $this->assertSame(12345, $product->fresh()->price_per_hour);
        $this->assertTrue(Schema::hasTable('stock_reservations'));
    }

    public function test_category_slug_is_unique_per_store_including_soft_deleted_rows(): void
    {
        $category = Category::findOrFail($this->categoryId);
        $category->delete();
        $this->assertRejected(fn () => Category::create([
            'name' => 'Duplicate', 'slug' => 'equipment', 'store_id' => $this->storeId,
        ]), '23505');
        $other = Store::create(['name' => 'Other']);
        $copy = Category::create(['name' => 'Equipment', 'slug' => 'equipment', 'store_id' => $other->id]);
        $this->assertTrue($copy->exists);
    }

    public function test_stock_product_is_unique(): void
    {
        $this->assertRejected(fn () => Stock::create([
            'product_id' => $this->productId, 'store_id' => $this->storeId, 'quantity' => 1,
        ]), '23505');
    }

    public static function orphanReferences(): array
    {
        return [
            'category store' => ['categories', 'store_id'],
            'category parent' => ['categories', 'parent_id'],
            'product store' => ['products', 'store_id'],
            'product category' => ['products', 'category_id'],
            'stock store' => ['stocks', 'store_id'],
            'stock product' => ['stocks', 'product_id'],
            'image product' => ['product_images', 'product_id'],
            'attribute product' => ['product_attributes', 'product_id'],
            'reservation store' => ['stock_reservations', 'store_id'],
            'reservation product' => ['stock_reservations', 'product_id'],
        ];
    }

    #[DataProvider('orphanReferences')]
    public function test_foreign_keys_reject_missing_references(string $table, string $column): void
    {
        StockReservation::create($this->reservation());
        $this->assertRejected(fn () => DB::table($table)->update([$column => (string) Uuid::uuid7()]), '23503');
    }

    public function test_hard_product_delete_cascades_images_and_attributes(): void
    {
        // Remove restrictive references first; images and attributes are owned by the product.
        Stock::where('product_id', $this->productId)->delete();
        Product::findOrFail($this->productId)->forceDelete();
        $this->assertDatabaseMissing('product_images', ['product_id' => $this->productId]);
        $this->assertDatabaseMissing('product_attributes', ['product_id' => $this->productId]);
    }
}
