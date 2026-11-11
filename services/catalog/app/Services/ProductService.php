<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Messaging\EventPublisherInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductAttributeRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockRepositoryInterface;
use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\DTO\CreateProductData;
use App\DTO\ProductFilters;
use App\DTO\UpdateProductData;
use App\Exceptions\NonNullableFieldException;
use App\Exceptions\ProductCategoryNotFoundException;
use App\Exceptions\ProductHasActiveReservationsException;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Cache\ProductCache;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

final class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StockRepositoryInterface $stockRepository,
        private readonly StockReservationRepositoryInterface $reservationRepository,
        private readonly EventPublisherInterface $eventPublisher,
        private readonly ProductCache $productCache,
    ) {
    }

    public function list(ProductFilters $filters): LengthAwarePaginator
    {
        if ($filters->isAvailable !== null) {
            $paginator = $this->productRepository->findByFilters($filters);

            return new Paginator(
                $paginator->items(),
                $paginator->total(),
                $filters->limit,
                $filters->page,
                ['path' => Paginator::resolveCurrentPath()],
            );
        }

        $payload = $this->productCache->rememberList(
            get_object_vars($filters),
            $filters->page,
            $filters->limit,
            function () use ($filters): array {
                $paginator = $this->productRepository->findByFilters($filters);

                return [
                    'items' => $paginator->items(),
                    'total' => $paginator->total(),
                ];
            },
        );

        /** @var array{items: array<int, Product>, total: int} $payload */
        return new Paginator(
            $payload['items'],
            $payload['total'],
            $filters->limit,
            $filters->page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    public function getPublic(string $productId): Product
    {
        /** @var Product $product */
        $product = $this->productCache->rememberCard(
            'public',
            $productId,
            fn (): Product => $this->productRepository->findDetailedById(
                $productId,
                null,
                true,
            ) ?? throw new ProductNotFoundException($productId),
        );

        return $product;
    }

    public function get(
        string $productId,
        ?string $storeId = null,
    ): Product {
        /** @var Product $product */
        $product = $this->productCache->rememberCard(
            'admin',
            $productId,
            fn (): Product => $this->findDetailedProductOrFail($productId, $storeId),
        );

        if ($storeId !== null && $product->store_id !== $storeId) {
            throw new ProductNotFoundException($productId);
        }

        return $product;
    }

    public function create(
        string $storeId,
        CreateProductData $data,
    ): Product {
        return DB::transaction(function () use ($storeId, $data): Product {
            $this->assertCategoryBelongsToStore($data->categoryId, $storeId);

            $product = $this->productRepository->createProduct(
                $storeId,
                $data,
            );

            $this->stockRepository->createStock(
                $product->id,
                $storeId,
                $data->quantity,
            );

            $this->attributeRepository->createProductAttribute(
                $data->attributes,
                $product->id,
            );

            $created = $this->findDetailedProductOrFail($product->id, $storeId);

            DB::afterCommit(function () use ($created): void {
                $this->productCache->invalidateLists();
                rescue(fn () => $this->eventPublisher->publish(
                    ProductCreated::fromModel($created),
                ), report: true);
            });

            return $created;
        });
    }

    public function update(
        string $storeId,
        string $productId,
        UpdateProductData $data,
    ): Product {
        return DB::transaction(function () use (
            $storeId,
            $productId,
            $data,
        ): Product {
            $this->findProductOrFail($productId, $storeId);

            $this->assertNonNullableFields($data);

            if ($data->categoryIdProvided) {
                $this->assertCategoryBelongsToStore($data->categoryId, $storeId);
            }

            $this->productRepository->updateProduct(
                $productId,
                $storeId,
                $data,
            );

            if ($data->attributes !== null) {
                $this->attributeRepository->deleteAllByProductId($productId);

                if ($data->attributes !== []) {
                    $this->attributeRepository->createProductAttribute(
                        $data->attributes,
                        $productId,
                    );
                }
            }

            $product = $this->findDetailedProductOrFail($productId, $storeId);

            DB::afterCommit(function () use ($product, $productId): void {
                $this->productCache->invalidateCard($productId);
                $this->productCache->invalidateLists();
                rescue(fn () => $this->eventPublisher->publish(
                    ProductUpdated::fromModel($product),
                ), report: true);
            });

            return $product;
        });
    }

    public function delete(
        string $storeId,
        string $productId,
    ): void {
        DB::transaction(function () use ($storeId, $productId): void {
            $product = $this->findProductOrFail($productId, $storeId);

            $this->stockRepository->lockByProductId($productId, $storeId);

            if ($this->reservationRepository->hasActiveByProduct($productId)) {
                throw new ProductHasActiveReservationsException($productId);
            }

            $this->productRepository->deleteProduct(
                $productId,
                $storeId,
            );

            DB::afterCommit(function () use ($product, $productId): void {
                $this->productCache->invalidateCard($productId);
                $this->productCache->invalidateLists();
                rescue(fn () => $this->eventPublisher->publish(
                    ProductDeleted::fromModel($product),
                ), report: true);
            });
        });
    }

    public function restore(
        string $storeId,
        string $productId,
    ): Product {
        return DB::transaction(function () use (
            $storeId,
            $productId,
        ): Product {
            $trashed = $this->productRepository->findTrashedByIdAndStore($productId, $storeId);

            if ($trashed === null) {
                throw new ProductNotFoundException($productId);
            }

            $this->productRepository->restoreProduct(
                $productId,
                $storeId,
            );

            $product = $this->findDetailedProductOrFail($productId, $storeId);

            DB::afterCommit(function () use ($product, $productId): void {
                $this->productCache->invalidateCard($productId);
                $this->productCache->invalidateLists();
                rescue(fn () => $this->eventPublisher->publish(
                    ProductUpdated::fromModel($product),
                ), report: true);
            });

            return $product;
        });
    }

    private function findDetailedProductOrFail(
        string $productId,
        ?string $storeId = null,
    ): Product {
        return $this->productRepository->findDetailedById(
            $productId,
            $storeId,
        ) ?? throw new ProductNotFoundException($productId);
    }

    private function findProductOrFail(
        string $productId,
        string $storeId,
    ): Product {
        return $this->productRepository->findById(
            $productId,
            $storeId,
        ) ?? throw new ProductNotFoundException($productId);
    }

    private function assertCategoryBelongsToStore(
        ?string $categoryId,
        string $storeId,
    ): void {
        if (
            $categoryId === null
            || $this->categoryRepository->findByStoreAndID(
                $categoryId,
                $storeId,
            ) === null
        ) {
            throw new ProductCategoryNotFoundException($categoryId);
        }
    }

    private function assertNonNullableFields(UpdateProductData $data): void
    {
        $nullableFields = [
            'name' => $data->nameProvided && $data->name === null,
            'slug' => $data->slugProvided && $data->slug === null,
            'category_id' => $data->categoryIdProvided && $data->categoryId === null,
            'price_per_hour' => $data->pricePerHourProvided && $data->pricePerHour === null,
            'deposit_amount' => $data->depositAmountProvided && $data->depositAmount === null,
            'is_active' => $data->isActiveProvided && $data->isActive === null,
        ];

        foreach ($nullableFields as $field => $isInvalid) {
            if ($isInvalid) {
                throw new NonNullableFieldException(
                    $field,
                    "The {$field} field cannot be null.",
                );
            }
        }
    }
}
