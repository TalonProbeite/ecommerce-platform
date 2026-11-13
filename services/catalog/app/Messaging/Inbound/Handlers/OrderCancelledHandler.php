<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Handlers;

use App\Contracts\Services\Inbound\OrderStockReservationServiceInterface;
use App\Messaging\Inbound\Data\OrderCancelledData;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OrderCancelledHandler implements InboundHandler
{
    public function __construct(
        private OrderStockReservationServiceInterface $service,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::ORDER_CANCELLED;
    }

    /**
     * @return class-string<OrderCancelledData>
     */
    public function dtoClass(): string
    {
        return OrderCancelledData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
    {
        if (! $dto instanceof OrderCancelledData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                OrderCancelledData::class,
                get_debug_type($dto),
            ));
        }

        return $this->service->handleCancelled($dto, $now);
    }
}
