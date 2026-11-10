<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Exceptions\InvalidImageFileException;
use App\Exceptions\ProductImageNotFoundException;
use App\Exceptions\ProductImageStorageException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\TooManyProductImagesException;
use App\Exceptions\UnsupportedImageTypeException;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;
use SplFileInfo;

/**
 * Product image lifecycle.
 *
 * Binary content lives in object storage; the database keeps only the object key
 * and metadata. Read methods are public and do not require a store ID. Mutating
 * methods are scoped to the store and maintain the single-main-image invariant.
 */
interface ProductImageServiceInterface
{
    /**
     * Return all images of a product, ordered by creation time.
     *
     * @return Collection<int, ProductImage>
     *
     * @throws ProductNotFoundException
     */
    public function list(string $productId): Collection;

    /**
     * Return one image of a product.
     *
     * @throws ProductNotFoundException
     * @throws ProductImageNotFoundException
     */
    public function get(string $productId, string $imageId): ProductImage;

    /**
     * Store a new image for a product. The first image of a product becomes its main image.
     *
     * @throws ProductNotFoundException
     * @throws InvalidImageFileException
     * @throws UnsupportedImageTypeException
     * @throws TooManyProductImagesException
     * @throws ProductImageStorageException
     */
    public function upload(string $storeId, string $productId, SplFileInfo $file): ProductImage;

    /**
     * Make the given image the main image of its product, clearing the previous one.
     *
     * @throws ProductNotFoundException
     * @throws ProductImageNotFoundException
     */
    public function setMain(string $storeId, string $productId, string $imageId): ProductImage;

    /**
     * Delete an image record. If it was the main image, promote the oldest remaining one.
     * The stored object is removed after the transaction commits.
     *
     * @throws ProductNotFoundException
     * @throws ProductImageNotFoundException
     */
    public function delete(string $storeId, string $productId, string $imageId): void;
}
