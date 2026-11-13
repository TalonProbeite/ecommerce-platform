<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Handlers;

use App\Contracts\Services\Inbound\OrderFulfilmentServiceInterface;
use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\InboundHandler;
use App\Messaging\Inbound\InboundRoutingKeys;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OrderIssuedHandler implements InboundHandler
{
    public function __construct(
        private OrderFulfilmentServiceInterface $service,
    ) {
    }

    public function routingKey(): string
    {
        return InboundRoutingKeys::ORDER_ISSUED;
    }

    /**
     * @return class-string<OrderIssuedData>
     */
    public function dtoClass(): string
    {
        return OrderIssuedData::class;
    }

    public function handle(object $dto, CarbonImmutable $now): ProcessingResult
    {
        if (! $dto instanceof OrderIssuedData) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                OrderIssuedData::class,
                get_debug_type($dto),
            ));
        }

        return $this->service->handleIssued($dto, $now);
    }
}
