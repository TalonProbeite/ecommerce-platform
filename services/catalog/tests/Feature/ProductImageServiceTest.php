<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ProductImageNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\TooManyProductImagesException;
use App\Infrastructure\Cache\ProductCache;
use App\Infrastructure\Storage\S3BucketGuard;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductImageService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use SplFileInfo;
use Tests\PostgresTestCase;

class ProductImageServiceTest extends PostgresTestCase
{
    private const DISK = 'images';

    private Store $store;
    private Product $product;
    private S3BucketGuard $bucketGuard;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISK);
        $this->store = Store::create(['name' => 'Tools']);
        $category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 500,
        ]);
        $this->bucketGuard = new S3BucketGuard();
        (new ReflectionProperty($this->bucketGuard, 'verifiedDisks'))->setValue($this->bucketGuard, [self::DISK => true]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_upload_persists_metadata_and_stores_object(): void
    {
        $file = $this->pngFile();

        try {
            $image = $this->service()->upload($this->store->id, $this->product->id, new SplFileInfo($file));
        } finally {
            unlink($file);
        }

        self::assertSame($this->product->id, $image->product_id);
        self::assertSame('image/png', $image->mime_type);
        self::assertGreaterThan(0, $image->size);
        self::assertTrue($image->is_main);
        self::assertSame(7, Uuid::fromString($image->id)->getVersion());
        self::assertStringStartsWith("stores/{$this->store->id}/products/{$this->product->id}/", $image->object_key);
        Storage::disk(self::DISK)->assertExists($image->object_key);
        self::assertSame([$image->id], $this->service()->list($this->product->id)->modelKeys());
        self::assertTrue($this->service()->get($this->product->id, $image->id)->is($image));
    }

    public function test_only_first_uploaded_image_is_main_and_set_main_replaces_it(): void
    {
        $first = $this->upload();
        $second = $this->upload();

        self::assertTrue($first->fresh()->is_main);
        self::assertFalse($second->fresh()->is_main);

        $main = $this->service()->setMain($this->store->id, $this->product->id, $second->id);
        self::assertTrue($main->is_main);
        self::assertFalse($first->fresh()->is_main);
        self::assertSame($second->id, $this->service()->list($this->product->id)->firstWhere('is_main', true)->id);
    }

    public function test_delete_main_image_promotes_the_oldest_remaining_image_and_removes_object(): void
    {
        $first = $this->upload();
        $second = $this->upload();

        $this->service()->delete($this->store->id, $this->product->id, $first->id);

        self::assertNull($this->service()->list($this->product->id)->firstWhere('id', $first->id));
        self::assertTrue($second->fresh()->is_main);
        Storage::disk(self::DISK)->assertMissing($first->object_key);
        Storage::disk(self::DISK)->assertExists($second->object_key);
    }

    public function test_upload_rejects_third_image_above_the_limit(): void
    {
        $this->upload();
        $this->upload();

        $file = $this->pngFile();

        $this->expectException(TooManyProductImagesException::class);

        try {
            $this->service()->upload($this->store->id, $this->product->id, new SplFileInfo($file));
        } finally {
            unlink($file);
            self::assertSame(2, $this->service()->list($this->product->id)->count());
        }
    }

    public function test_mutations_are_scoped_to_the_store(): void
    {
        $image = $this->upload();
        $otherStore = Store::create(['name' => 'Other']);
        $file = $this->pngFile();

        try {
            foreach ([
                fn () => $this->service()->upload($otherStore->id, $this->product->id, new SplFileInfo($file)),
                fn () => $this->service()->setMain($otherStore->id, $this->product->id, $image->id),
                fn () => $this->service()->delete($otherStore->id, $this->product->id, $image->id),
            ] as $operation) {
                try {
                    $operation();
                    self::fail('Mutating images from another store must fail.');
                } catch (ProductNotFoundException) {
                }
            }
        } finally {
            unlink($file);
        }

        self::assertTrue($image->fresh()->is_main);
    }

    public function test_missing_image_is_rejected(): void
    {
        $this->expectException(ProductImageNotFoundException::class);

        $this->service()->get($this->product->id, (string) Uuid::uuid7());
    }

    public function test_missing_product_is_rejected_by_reads(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service()->list((string) Uuid::uuid7());
    }

    private function service(): ProductImageService
    {
        return new ProductImageService(
            new ProductImageRepository(),
            new ProductRepository(),
            $this->bucketGuard,
            self::DISK,
            2,
            ['image/png' => 'png'],
            app(ProductCache::class),
        );
    }

    private function upload(): ProductImage
    {
        $file = $this->pngFile();

        try {
            return $this->service()->upload($this->store->id, $this->product->id, new SplFileInfo($file));
        } finally {
            unlink($file);
        }
    }

    private function pngFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-image-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        return $path;
    }
}
