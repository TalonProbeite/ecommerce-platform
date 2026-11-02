<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProductImageRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Services\ProductImageServiceInterface;
use App\Exceptions\InvalidImageFileException;
use App\Exceptions\ProductImageNotFoundException;
use App\Exceptions\ProductImageStorageException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\TooManyProductImagesException;
use App\Exceptions\UnsupportedImageTypeException;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use SplFileInfo;

final class ProductImageService implements ProductImageServiceInterface
{
    private const DISK = 'seaweedfs';

    private const MAX_IMAGES_PER_PRODUCT = 10;

    /**
     * Allowed MIME types mapped to the file extension used in the object key.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ProductImageRepositoryInterface $imageRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function list(string $productId): Collection
    {
        $this->findProductOrFail($productId);

        return $this->imageRepository->listForProduct($productId);
    }

    public function get(string $productId, string $imageId): ProductImage
    {
        $this->findProductOrFail($productId);

        return $this->findImageOrFail($productId, $imageId);
    }

    public function upload(string $storeId, string $productId, SplFileInfo $file): ProductImage
    {
        // Validate before opening a transaction so invalid input never holds row locks.
        $this->assertReadableFile($file);

        $mimeType = $this->detectMimeType($file);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType]
            ?? throw new UnsupportedImageTypeException($mimeType);
        $size = $this->readSize($file);

        return DB::transaction(function () use ($storeId, $productId, $file, $mimeType, $extension, $size): ProductImage {
            $this->lockProductOrFail($storeId, $productId);

            $existing = $this->imageRepository->listForProduct($productId);

            if ($existing->count() >= self::MAX_IMAGES_PER_PRODUCT) {
                throw new TooManyProductImagesException($productId, self::MAX_IMAGES_PER_PRODUCT);
            }

            $imageId = (string) Uuid::uuid7();
            $objectKey = $this->buildObjectKey($storeId, $productId, $imageId, $extension);

            $image = $this->imageRepository->create([
                'id' => $imageId,
                'product_id' => $productId,
                'object_key' => $objectKey,
                'mime_type' => $mimeType,
                'size' => $size,
                'is_main' => ! $existing->contains('is_main', true),
            ]);

            // Write the object after the row: if the write fails, the transaction rolls back the row.
            $this->storeObject($objectKey, $file);

            return $image;
        });
    }

    public function setMain(string $storeId, string $productId, string $imageId): ProductImage
    {
        return DB::transaction(function () use ($storeId, $productId, $imageId): ProductImage {
            $this->lockProductOrFail($storeId, $productId);

            $image = $this->findImageOrFail($productId, $imageId);

            if ($image->is_main) {
                return $image;
            }

            // Clear first: the partial unique index rejects a second main image at statement time.
            $this->imageRepository->clearMainForProduct($productId);

            return $this->imageRepository->update($image, ['is_main' => true]);
        });
    }

    public function delete(string $storeId, string $productId, string $imageId): void
    {
        DB::transaction(function () use ($storeId, $productId, $imageId): void {
            $this->lockProductOrFail($storeId, $productId);

            $image = $this->findImageOrFail($productId, $imageId);
            $objectKey = $image->object_key;
            $wasMain = $image->is_main;

            $this->imageRepository->delete($image);

            if ($wasMain) {
                $replacement = $this->imageRepository->firstForProduct($productId);

                if ($replacement !== null) {
                    $this->imageRepository->update($replacement, ['is_main' => true]);
                }
            }

            // Remove the object only after a successful commit, so a rollback keeps the file.
            DB::afterCommit(static function () use ($objectKey): void {
                Storage::disk(self::DISK)->delete($objectKey);
            });
        });
    }

    /**
     * Lock the product row for the rest of the transaction.
     * Serializes all main-image changes for the same product.
     *
     * @throws ProductNotFoundException
     */
    private function lockProductOrFail(string $storeId, string $productId): void
    {
        if ($this->productRepository->lockById($productId, $storeId) === null) {
            throw new ProductNotFoundException($productId);
        }
    }

    /**
     * @throws ProductNotFoundException
     */
    private function findProductOrFail(string $productId, ?string $storeId = null): void
    {
        if ($this->productRepository->findById($productId, $storeId) === null) {
            throw new ProductNotFoundException($productId);
        }
    }

    /**
     * @throws ProductImageNotFoundException
     */
    private function findImageOrFail(string $productId, string $imageId): ProductImage
    {
        return $this->imageRepository->findByIdForProduct($imageId, $productId)
            ?? throw new ProductImageNotFoundException($imageId);
    }

    /**
     * @throws InvalidImageFileException
     */
    private function assertReadableFile(SplFileInfo $file): void
    {
        if (! $file->isFile() || ! $file->isReadable()) {
            throw new InvalidImageFileException('File is not readable.');
        }
    }

    /**
     * @throws InvalidImageFileException
     */
    private function detectMimeType(SplFileInfo $file): string
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());

        if ($mimeType === false) {
            throw new InvalidImageFileException('Unable to detect file type.');
        }

        return $mimeType;
    }

    /**
     * @throws InvalidImageFileException
     */
    private function readSize(SplFileInfo $file): int
    {
        $size = $file->getSize();

        if ($size === false) {
            throw new InvalidImageFileException('Unable to read file size.');
        }

        return $size;
    }

    /**
     * Stream the file to object storage.
     *
     * @throws InvalidImageFileException
     * @throws ProductImageStorageException
     */
    private function storeObject(string $objectKey, SplFileInfo $file): void
    {
        $stream = fopen($file->getPathname(), 'rb');

        if ($stream === false) {
            throw new InvalidImageFileException('Unable to open file for reading.');
        }

        $stored = Storage::disk(self::DISK)->put($objectKey, $stream);

        fclose($stream);

        if ($stored !== true) {
            throw new ProductImageStorageException($objectKey);
        }
    }

    private function buildObjectKey(string $storeId, string $productId, string $imageId, string $extension): string
    {
        return sprintf('stores/%s/products/%s/%s.%s', $storeId, $productId, $imageId, $extension);
    }
}
