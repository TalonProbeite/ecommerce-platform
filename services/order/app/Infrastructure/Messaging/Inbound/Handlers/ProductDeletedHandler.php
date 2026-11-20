<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Catalog\SyncProductSnapshot\Commands\ProductDeleteCommand;
use App\Application\Catalog\SyncProductSnapshot\SyncProductSnapshotUseCase;
use App\Infrastructure\Messaging\Inbound\Data\ProductDeletedData;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;

/**
 * @implements InboundHandler<ProductDeletedData>
 */
final readonly class ProductDeletedHandler implements InboundHandler
{
    public function __construct(
        private SyncProductSnapshotUseCase $useCase,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::PRODUCT_DELETED;
    }

    /**
     * @return class-string<ProductDeletedData>
     */
    public function dtoClass(): string
    {
        return ProductDeletedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): void
    {
        $this->useCase->delete(new ProductDeleteCommand(
            eventId: $dto->eventId,
            occurredAt: $dto->occurredAt,
            productId: $dto->productId,
            storeId: $dto->storeId,
        ), $now);
    }
}
