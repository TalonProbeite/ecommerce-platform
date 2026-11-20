<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Handlers;

use App\Contracts\Services\Inbound\OrderFulfilmentServiceInterface;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;

/**
 * @implements InboundHandler<OrderReturnedData>
 */
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
        return $this->service->handleReturned($dto, $now);
    }
}
