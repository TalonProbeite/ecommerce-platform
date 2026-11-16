<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Catalog\SyncProductSnapshot\Commands\ProductUpsertCommand;
use App\Application\Catalog\SyncProductSnapshot\SyncProductSnapshotUseCase;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class ProductCreatedHandler implements InboundHandler
{
    public function __construct(
        private SyncProductSnapshotUseCase $useCase,
        private LoggerInterface $logger,
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
        if (! $dto instanceof ProductUpsertData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                ProductUpsertData::class,
                get_debug_type($dto),
            ));
        }

        try {
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
        } catch (OrderNotFound $e) {
            $this->logger->error($e->getMessage(), [
                'order_id' => $e->orderId,
                'event_id' => $dto->eventId,
                'exception' => $e,
            ]);

            throw new InvalidMessageException(
                ['order_id' => ['The order does not exist.']],
                $e->getMessage(),
            );
        } catch (StoreMismatch $e) {
            $this->logger->error($e->getMessage(), [
                'order_id' => $e->orderId,
                'event_id' => $dto->eventId,
                'expected_store_id' => $e->expectedStoreId,
                'actual_store_id' => $e->actualStoreId,
                'exception' => $e,
            ]);

            throw new InvalidMessageException(
                ['store_id' => ['The event store does not match the order store.']],
                $e->getMessage(),
            );
        }
    }
}
