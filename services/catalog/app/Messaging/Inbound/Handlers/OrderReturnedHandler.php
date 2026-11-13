<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Handlers;

use App\Contracts\Services\Inbound\OrderFulfilmentServiceInterface;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OrderReturnedHandler implements InboundHandler
{
    public function __construct(
        private OrderFulfilmentServiceInterface $service,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::ORDER_RETURNED;
    }

    /**
     * @return class-string<OrderReturnedData>
     */
    public function dtoClass(): string
    {
        return OrderReturnedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
    {
        if (! $dto instanceof OrderReturnedData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                OrderReturnedData::class,
                get_debug_type($dto),
            ));
        }

        return $this->service->handleReturned($dto, $now);
    }
}
