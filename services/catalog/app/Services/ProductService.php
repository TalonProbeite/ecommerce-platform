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
use App\Exceptions\ProductCategoryNotFoundException;
use App\Exceptions\ProductHasActiveReservationsException;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StockRepositoryInterface $stockRepository,
        private readonly StockReservationRepositoryInterface $reservationRepository,
        private readonly EventPublisherInterface $eventPublisher,
    ) {
    }

    public function list(ProductFilters $filters): LengthAwarePaginator
    {
        return $this->productRepository->findByFilters($filters);
    }

    public function get(
        string $productId,
        ?string $storeId = null,
    ): Product {
        return $this->productRepository->findDetailedById(
            $productId,
            $storeId,
        ) ?? $this->throwProductNotFound($productId);
    }

    public function create(
        string $storeId,
        CreateProductData $data,
    ): Product {
        return DB::transaction(function () use ($storeId, $data): Product {
            $this->assertCategoryBelongsToStore($data->categoryId, $storeId);

            $productId = (string) Uuid::uuid7();

            $this->productRepository->createProduct(
                $productId,
                $storeId,
                $data,
            );

            $this->stockRepository->createStock(
                $productId,
                $storeId,
                $data->quantity,
            );

            $this->attributeRepository->createProductAttribute(
                $data->attributes,
                $productId,
            );

            $product = $this->get($productId, $storeId);

            DB::afterCommit(function () use ($product): void {
                $this->eventPublisher->publish(
                    ProductCreated::fromModel($product),
                );
            });

            return $product;
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

            $product = $this->get($productId, $storeId);

            DB::afterCommit(function () use ($product): void {
                $this->eventPublisher->publish(
                    ProductUpdated::fromModel($product),
                );
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

            DB::afterCommit(function () use ($product): void {
                $this->eventPublisher->publish(
                    ProductDeleted::fromModel($product),
                );
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
            $this->productRepository->restoreProduct(
                $productId,
                $storeId,
            );

            $product = $this->get($productId, $storeId);

            DB::afterCommit(function () use ($product): void {
                $this->eventPublisher->publish(
                    ProductUpdated::fromModel($product),
                );
            });

            return $product;
        });
    }

    private function findProductOrFail(
        string $productId,
        string $storeId,
    ): Product {
        return $this->productRepository->findById(
            $productId,
            $storeId,
        ) ?? $this->throwProductNotFound($productId);
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
                throw ValidationException::withMessages([
                    $field => "The {$field} field cannot be null.",
                ]);
            }
        }
    }

    private function throwProductNotFound(string $productId): never
    {
        throw (new ModelNotFoundException())
            ->setModel(Product::class, [$productId]);
    }
}
