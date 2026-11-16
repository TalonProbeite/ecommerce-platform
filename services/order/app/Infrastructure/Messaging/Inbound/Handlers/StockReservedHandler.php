<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Handlers;

use App\Application\Saga\OnStockReserved;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use App\Infrastructure\Messaging\Inbound\InboundHandler;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class StockReservedHandler implements InboundHandler
{
    public function __construct(
        private OnStockReserved $useCase,
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

        $this->useCase->handle($dto, $now);
    }
}
