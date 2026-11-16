<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Application\Saga\Commands\StockReservedCommand;
use App\Application\Saga\OnStockReserved;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class StockReservedHandler implements InboundHandler
{
    public function __construct(
        private OnStockReserved $useCase,
        private LoggerInterface $logger,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::STOCK_RESERVED;
    }

    /**
     * @return class-string<StockReservedData>
     */
    public function dtoClass(): string
    {
        return StockReservedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): void
    {
        if (! $dto instanceof StockReservedData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                StockReservedData::class,
                get_debug_type($dto),
            ));
        }

        try {
            $this->useCase->handle(new StockReservedCommand(
                eventId: $dto->eventId,
                occurredAt: $dto->occurredAt,
                orderId: $dto->orderId,
                storeId: $dto->storeId,
            ), $now);
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
