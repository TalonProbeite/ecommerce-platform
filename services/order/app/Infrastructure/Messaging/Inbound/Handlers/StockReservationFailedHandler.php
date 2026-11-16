<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\StoreMismatch;
use App\Application\Saga\Commands\StockReservationFailedCommand;
use App\Application\Saga\OnStockReservationFailed;
use App\Application\Saga\StockRejectionReason;
use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class StockReservationFailedHandler implements InboundHandler
{
    public function __construct(
        private OnStockReservationFailed $useCase,
        private LoggerInterface $logger,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::STOCK_RESERVATION_FAILED;
    }

    /**
     * @return class-string<StockReservationFailedData>
     */
    public function dtoClass(): string
    {
        return StockReservationFailedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): void
    {
        if (! $dto instanceof StockReservationFailedData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                StockReservationFailedData::class,
                get_debug_type($dto),
            ));
        }

        try {
            $this->useCase->handle(new StockReservationFailedCommand(
                eventId: $dto->eventId,
                occurredAt: $dto->occurredAt,
                orderId: $dto->orderId,
                storeId: $dto->storeId,
                reason: StockRejectionReason::from($dto->reason),
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
