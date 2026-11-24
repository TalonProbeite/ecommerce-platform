<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use App\Infrastructure\Messaging\Inbound\Exceptions\UnknownRoutingKeyException;
use App\Infrastructure\Messaging\Inbound\InboundHandlerRegistry;
use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InboundHandlerRegistrationTest extends TestCase
{
    #[Test]
    public function the_container_registers_a_handler_for_every_inbound_routing_key(): void
    {
        $registry = $this->app->make(InboundHandlerRegistry::class);

        foreach (InboundRoutingKeys::all() as $routingKey) {
            self::assertTrue($registry->has($routingKey), sprintf('No handler registered for "%s".', $routingKey));
        }

        self::assertSame(
            StockReservedData::class,
            $registry->get(InboundRoutingKeys::STOCK_RESERVED)->dtoClass(),
        );
        self::assertSame(
            StockReservationFailedData::class,
            $registry->get(InboundRoutingKeys::STOCK_RESERVATION_FAILED)->dtoClass(),
        );
    }

    #[Test]
    public function an_unregistered_routing_key_is_rejected_by_the_registry(): void
    {
        $registry = $this->app->make(InboundHandlerRegistry::class);

        $this->expectException(UnknownRoutingKeyException::class);

        $registry->get('stock.changed');
    }
}
