<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Saga\OnStockReservationFailed;
use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class StockReservationFailedHandler implements InboundHandler
{
    public function __construct(
        private OnStockReservationFailed $useCase,
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

        $this->useCase->handle($dto, $now);
    }
}
