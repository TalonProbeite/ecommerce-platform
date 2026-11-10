<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

class CatalogHttpBehaviorTest extends PostgresTestCase
{
    private Store $store;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->store = Store::create(['name' => 'Tools']);
        $this->category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
    }

    public function test_duplicate_category_slug_in_same_store_is_rejected(): void
    {
        $response = $this->postJson("/api/v1/admin/stores/{$this->store->id}/categories", [
            'name' => 'Other',
            'slug' => 'tools',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_category_slug_in_another_store_is_allowed(): void
    {
        $otherStore = Store::create(['name' => 'Other']);

        $response = $this->postJson("/api/v1/admin/stores/{$otherStore->id}/categories", [
            'name' => 'Tools',
            'slug' => 'tools',
        ]);

        $response->assertCreated();
    }

    public function test_category_update_keeps_its_own_slug(): void
    {
        $response = $this->patchJson(
            "/api/v1/admin/stores/{$this->store->id}/categories/{$this->category->id}",
            ['name' => 'Renamed', 'slug' => 'tools'],
        );

        $response->assertOk();
    }

    public function test_duplicate_product_slug_in_same_store_is_rejected(): void
    {
        $this->createProduct('drill');

        $response = $this->postJson("/api/v1/admin/stores/{$this->store->id}/products", [
            'name' => 'Drill',
            'slug' => 'drill',
            'category_id' => $this->category->id,
            'price_per_hour' => 100,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_product_slug_in_another_store_is_allowed(): void
    {
        $otherStore = Store::create(['name' => 'Other']);
        $otherCategory = Category::create([
            'store_id' => $otherStore->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->createProduct('drill');

        $response = $this->postJson("/api/v1/admin/stores/{$otherStore->id}/products", [
            'name' => 'Drill',
            'slug' => 'drill',
            'category_id' => $otherCategory->id,
            'price_per_hour' => 100,
            'quantity' => 1,
        ]);

        $response->assertCreated();
    }

    public function test_product_update_keeps_its_own_slug(): void
    {
        $product = $this->createProduct('drill');

        $response = $this->patchJson(
            "/api/v1/admin/stores/{$this->store->id}/products/{$product->id}",
            ['slug' => 'drill'],
        );

        $response->assertOk();
    }

    public function test_missing_category_returns_not_found(): void
    {
        $response = $this->getJson(
            "/api/v1/stores/{$this->store->id}/categories/" . Uuid::uuid7(),
        );

        $response->assertStatus(404)->assertJson(['error' => 'Category not found!']);
    }

    public function test_non_uuid_store_route_returns_not_found(): void
    {
        $this->getJson('/api/v1/stores/not-a-uuid')->assertStatus(404);
    }

    private function createProduct(string $slug): Product
    {
        return Product::create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => 'Drill',
            'slug' => $slug,
            'price_per_hour' => 100,
        ]);
    }
}
