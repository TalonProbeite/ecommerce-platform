<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Handlers;

use App\Contracts\Services\Inbound\OrderStockReservationServiceInterface;
use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OrderCreatedHandler implements InboundHandler
{
    public function __construct(
        private OrderStockReservationServiceInterface $service,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::ORDER_CREATED;
    }

    /**
     * @return class-string<OrderCreatedData>
     */
    public function dtoClass(): string
    {
        return OrderCreatedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
    {
        if (! $dto instanceof OrderCreatedData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                OrderCreatedData::class,
                get_debug_type($dto),
            ));
        }

        return $this->service->handleCreated($dto, $now);
    }
}
