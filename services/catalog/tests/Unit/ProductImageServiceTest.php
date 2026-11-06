<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Exceptions\InvalidImageFileException;
use App\Exceptions\ProductImageNotFoundException;
use App\Exceptions\ProductImageStorageException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\TooManyProductImagesException;
use App\Exceptions\UnsupportedImageTypeException;
use App\Infrastructure\Cache\ProductCache;
use App\Infrastructure\Storage\S3BucketGuard;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use Tests\TestCase;

class ProductImageServiceTest extends TestCase
{
    private const DISK = 'images';

    private ProductCache $productCache;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->productCache = $this->cache();
        Storage::fake(self::DISK);
        DB::shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());
        DB::shouldReceive('afterCommit')
            ->andReturnUsing(fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->tempFiles = [];

        Mockery::close();

        parent::tearDown();
    }

    public function test_list_returns_images_for_existing_product(): void
    {
        $images = new Collection([$this->image('image-1', 'object-1')]);
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', null)
            ->andReturn(new Product());
        $imageRepository->shouldReceive('listForProduct')
            ->once()
            ->with('product-123')
            ->andReturn($images);

        self::assertSame($images, $this->service($imageRepository, $productRepository)->list('product-123'));
    }

    public function test_list_throws_when_product_is_missing(): void
    {
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('missing', null)
            ->andReturnNull();
        $imageRepository->shouldNotReceive('listForProduct');

        $this->expectException(ProductNotFoundException::class);

        $this->service($imageRepository, $productRepository)->list('missing');
    }

    public function test_get_returns_image_scoped_to_product(): void
    {
        $image = $this->image('image-1', 'object-1');
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('findById')
            ->once()
            ->with('product-123', null)
            ->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')
            ->once()
            ->with('image-1', 'product-123')
            ->andReturn($image);

        self::assertSame($image, $this->service($imageRepository, $productRepository)->get('product-123', 'image-1'));
    }

    public function test_get_throws_when_image_is_missing(): void
    {
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('findById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')->once()->andReturnNull();

        $this->expectException(ProductImageNotFoundException::class);

        $this->service($imageRepository, $productRepository)->get('product-123', 'missing');
    }

    public function test_upload_stores_first_image_as_main(): void
    {
        $file = $this->pngFile();
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $guard = $this->guard();

        $productRepository->shouldReceive('lockById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn(new Product());
        $imageRepository->shouldReceive('listForProduct')
            ->once()
            ->with('product-123')
            ->andReturn(new Collection());

        $stored = null;
        $imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use (&$stored): bool {
                $stored = $data;

                return Uuid::isValid($data['id'])
                    && $data['product_id'] === 'product-123'
                    && $data['mime_type'] === 'image/png'
                    && $data['size'] > 0
                    && $data['is_main'] === true
                    && preg_match('#^stores/store-123/products/product-123/[0-9a-f-]+\.png$#', $data['object_key']) === 1;
            }))
            ->andReturnUsing(fn (array $data): ProductImage => new ProductImage($data));

        $image = $this->service($imageRepository, $productRepository, $guard)->upload(
            'store-123',
            'product-123',
            new \SplFileInfo($file),
        );

        self::assertSame($stored['object_key'], $image->object_key);
        Storage::disk(self::DISK)->assertExists($image->object_key);
    }

    public function test_upload_does_not_replace_the_existing_main_image(): void
    {
        $file = $this->pngFile();
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $guard = $this->guard();

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('listForProduct')
            ->once()
            ->andReturn(new Collection([$this->image('main', 'object-main', true)]));

        $imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['is_main'] === false))
            ->andReturnUsing(fn (array $data): ProductImage => new ProductImage($data));

        $this->service($imageRepository, $productRepository, $guard)->upload('store-123', 'product-123', new \SplFileInfo($file));

        $this->addToAssertionCount(1);
    }

    public function test_upload_rejects_too_many_images(): void
    {
        $file = $this->pngFile();
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $guard = $this->guard();

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('listForProduct')
            ->once()
            ->andReturn(new Collection([$this->image('one', 'object-1'), $this->image('two', 'object-2')]));
        $imageRepository->shouldNotReceive('create');

        $this->expectException(TooManyProductImagesException::class);

        $this->service($imageRepository, $productRepository, $guard)->upload('store-123', 'product-123', new \SplFileInfo($file));
    }

    public function test_upload_rejects_missing_product(): void
    {
        $file = $this->pngFile();
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $guard = $this->guard();

        $productRepository->shouldReceive('lockById')
            ->once()
            ->with('missing', 'store-123')
            ->andReturnNull();
        $imageRepository->shouldNotReceive('listForProduct');

        $this->expectException(ProductNotFoundException::class);

        $this->service($imageRepository, $productRepository, $guard)->upload('store-123', 'missing', new \SplFileInfo($file));
    }

    public function test_upload_rejects_unreadable_file_before_database_access(): void
    {
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldNotReceive('lockById');

        $this->expectException(InvalidImageFileException::class);

        $this->service($imageRepository, $productRepository)->upload(
            'store-123',
            'product-123',
            new \SplFileInfo('/missing/catalog-image.png'),
        );
    }

    public function test_upload_rejects_unsupported_mime_type(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-text-');
        file_put_contents($path, 'not an image');
        $this->tempFiles[] = $path;
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldNotReceive('lockById');

        $this->expectException(UnsupportedImageTypeException::class);

        $this->service($imageRepository, $productRepository)->upload(
            'store-123',
            'product-123',
            new \SplFileInfo($path),
        );
    }

    public function test_upload_throws_when_storage_write_fails(): void
    {
        $file = $this->pngFile();
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $guard = $this->guard();
        $filesystem = Mockery::mock(Filesystem::class);

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('listForProduct')->once()->andReturn(new Collection());
        $imageRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(fn (array $data): ProductImage => new ProductImage($data));
        $filesystem->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with(self::DISK)->andReturn($filesystem);

        $this->expectException(ProductImageStorageException::class);

        $this->service($imageRepository, $productRepository, $guard)->upload('store-123', 'product-123', new \SplFileInfo($file));
    }

    public function test_set_main_clears_previous_main_image(): void
    {
        $image = $this->image('image-1', 'object-1');
        $updated = $this->image('image-1', 'object-1', true);
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('lockById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')
            ->once()
            ->with('image-1', 'product-123')
            ->andReturn($image);
        $imageRepository->shouldReceive('clearMainForProduct')
            ->once()
            ->with('product-123');
        $imageRepository->shouldReceive('update')
            ->once()
            ->with($image, ['is_main' => true])
            ->andReturn($updated);

        self::assertSame(
            $updated,
            $this->service($imageRepository, $productRepository)->setMain('store-123', 'product-123', 'image-1'),
        );
    }

    public function test_set_main_flushes_affected_product_card_and_lists(): void
    {
        $image = $this->image('image-1', 'object-1');
        $updated = $this->image('image-1', 'object-1', true);
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $cache = $this->cache();
        $this->productCache = $cache;

        $cache->rememberCard('product-123', fn (): string => 'old-card');
        $cache->rememberCard('product-999', fn (): string => 'other-card');
        $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'old-list');

        $productRepository->shouldReceive('lockById')
            ->once()
            ->with('product-123', 'store-123')
            ->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')
            ->once()
            ->with('image-1', 'product-123')
            ->andReturn($image);
        $imageRepository->shouldReceive('clearMainForProduct')
            ->once()
            ->with('product-123');
        $imageRepository->shouldReceive('update')
            ->once()
            ->with($image, ['is_main' => true])
            ->andReturn($updated);

        $this->service($imageRepository, $productRepository)
            ->setMain('store-123', 'product-123', 'image-1');

        self::assertSame('new-card', $cache->rememberCard('product-123', fn (): string => 'new-card'));
        self::assertSame('other-card', $cache->rememberCard('product-999', fn (): string => 'refreshed-other-card'));
        self::assertSame('new-list', $cache->rememberList(['store_id' => 'store-123'], 1, 20, fn (): string => 'new-list'));
    }

    public function test_set_main_is_idempotent_for_the_current_main_image(): void
    {
        $image = $this->image('image-1', 'object-1', true);
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')->once()->andReturn($image);
        $imageRepository->shouldNotReceive('clearMainForProduct');
        $imageRepository->shouldNotReceive('update');

        self::assertSame(
            $image,
            $this->service($imageRepository, $productRepository)->setMain('store-123', 'product-123', 'image-1'),
        );
    }

    public function test_delete_main_image_promotes_oldest_remaining_image_and_removes_object(): void
    {
        $image = $this->image('image-1', 'object-main', true);
        $replacement = $this->image('image-2', 'object-replacement');
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        Storage::disk(self::DISK)->put('object-main', 'binary');

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')
            ->once()
            ->with('image-1', 'product-123')
            ->andReturn($image);
        $imageRepository->shouldReceive('delete')->once()->with($image);
        $imageRepository->shouldReceive('firstForProduct')
            ->once()
            ->with('product-123')
            ->andReturn($replacement);
        $imageRepository->shouldReceive('update')
            ->once()
            ->with($replacement, ['is_main' => true])
            ->andReturn($replacement);

        $this->service($imageRepository, $productRepository)->delete('store-123', 'product-123', 'image-1');

        Storage::disk(self::DISK)->assertMissing('object-main');
    }

    public function test_delete_non_main_image_does_not_promote_a_replacement(): void
    {
        $image = $this->image('image-1', 'object-1');
        $imageRepository = Mockery::mock(ProductImageRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        Storage::disk(self::DISK)->put('object-1', 'binary');

        $productRepository->shouldReceive('lockById')->once()->andReturn(new Product());
        $imageRepository->shouldReceive('findByIdForProduct')->once()->andReturn($image);
        $imageRepository->shouldReceive('delete')->once()->with($image);
        $imageRepository->shouldNotReceive('firstForProduct');
        $imageRepository->shouldNotReceive('update');

        $this->service($imageRepository, $productRepository)->delete('store-123', 'product-123', 'image-1');

        Storage::disk(self::DISK)->assertMissing('object-1');
    }

    private function service(
        ProductImageRepositoryInterface $imageRepository,
        ProductRepositoryInterface $productRepository,
        ?S3BucketGuard $guard = null,
    ): ProductImageService {
        return new ProductImageService(
            $imageRepository,
            $productRepository,
            $guard ?? $this->guard(),
            self::DISK,
            2,
            ['image/png' => 'png'],
            $this->productCache,
        );
    }

    private function cache(): ProductCache
    {
        return new ProductCache(
            300,
            600,
            86400,
            Mockery::mock(ProductRepositoryInterface::class),
        );
    }

    private function guard(): S3BucketGuard
    {
        $guard = new S3BucketGuard();
        (new ReflectionProperty($guard, 'verifiedDisks'))->setValue($guard, [self::DISK => true]);

        return $guard;
    }

    private function image(string $id, string $objectKey, bool $isMain = false): ProductImage
    {
        return new ProductImage([
            'id' => $id,
            'product_id' => 'product-123',
            'object_key' => $objectKey,
            'mime_type' => 'image/png',
            'size' => 10,
            'is_main' => $isMain,
        ]);
    }

    private function pngFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-image-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $this->tempFiles[] = $path;

        return $path;
    }
}
