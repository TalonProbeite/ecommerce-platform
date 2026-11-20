<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Catalog\SyncProductSnapshot\Commands\ProductUpsertCommand;
use App\Application\Catalog\SyncProductSnapshot\SyncProductSnapshotUseCase;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;

/**
 * @implements InboundHandler<ProductUpsertData>
 */
final readonly class ProductCreatedHandler implements InboundHandler
{
    public function __construct(
        private SyncProductSnapshotUseCase $useCase,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::PRODUCT_CREATED;
    }

    /**
     * @return class-string<ProductUpsertData>
     */
    public function dtoClass(): string
    {
        return ProductUpsertData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): void
    {
        $this->useCase->upsert(new ProductUpsertCommand(
            eventId: $dto->eventId,
            occurredAt: $dto->occurredAt,
            productId: $dto->productId,
            storeId: $dto->storeId,
            categoryId: $dto->categoryId,
            name: $dto->name,
            pricePerHour: $dto->pricePerHour,
            depositAmount: $dto->depositAmount,
            isActive: $dto->isActive,
        ), $now, InboundRoutingKeys::PRODUCT_CREATED);
    }
}
