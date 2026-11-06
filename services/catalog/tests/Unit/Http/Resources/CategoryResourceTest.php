<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    public function test_children_absent_when_recursive_children_not_loaded(): void
    {
        $category = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $category->created_at = now();
        $category->updated_at = now();

        $data = (new CategoryResource($category))->response()->getData(true)['data'];

        self::assertArrayNotHasKey('children', $data);
        self::assertArrayNotHasKey('ancestors', $data);
        self::assertSame('Tools', $data['name']);
        self::assertSame('tools', $data['slug']);
    }

    public function test_children_present_when_recursive_children_loaded(): void
    {
        $category = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $category->created_at = now();
        $category->updated_at = now();

        $child = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => $category->store_id,
            'parent_id' => $category->id,
            'name' => 'Power Tools',
            'slug' => 'power-tools',
        ]);
        $child->created_at = now();
        $child->updated_at = now();

        $category->setRelation('recursiveChildren', new Collection([$child]));

        $data = (new CategoryResource($category))->response()->getData(true)['data'];

        self::assertArrayHasKey('children', $data);
        self::assertCount(1, $data['children']);
        self::assertSame('Power Tools', $data['children'][0]['name']);
    }

    public function test_ancestors_present_when_parent_chain_loaded(): void
    {
        $root = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => (string) Uuid::uuid7(),
            'name' => 'Root Category',
            'slug' => 'root',
        ]);
        $parent = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => $root->store_id,
            'parent_id' => $root->id,
            'name' => 'Middle Category',
            'slug' => 'middle',
        ]);
        $category = new Category([
            'id' => (string) Uuid::uuid7(),
            'store_id' => $root->store_id,
            'parent_id' => $parent->id,
            'name' => 'Leaf Category',
            'slug' => 'leaf',
        ]);

        $parent->setRelation('recursiveParent', $root);
        $category->setRelation('recursiveParent', $parent);

        $data = (new CategoryResource($category))->response()->getData(true)['data'];

        self::assertArrayHasKey('ancestors', $data);
        self::assertSame([
            ['id' => $root->id, 'name' => 'Root Category'],
            ['id' => $parent->id, 'name' => 'Middle Category'],
        ], $data['ancestors']);
    }
}
