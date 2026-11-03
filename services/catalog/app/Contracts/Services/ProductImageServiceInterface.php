<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\ProductImage;
use SplFileInfo;

/**
 * Enforce image/product/store ownership. Missing entities, invalid files and
 * storage failures fail the operation; no HTTP or messaging types are required.
 */
interface ProductImageServiceInterface
{
    /**
     * Upload a readable local file to S3-compatible storage and persist its object
     * key in product_images.path. The path is an object key, never an external URL.
     */
    public function upload(SplFileInfo $file, string $productId, string $storeId): ProductImage;

    /** Delete the image record and its stored object. */
    public function delete(string $imageId, string $productId, string $storeId): void;

    /** Atomically replace the main image, respecting the one-main-image unique index. */
    public function setMain(string $imageId, string $productId, string $storeId): ProductImage;
}
