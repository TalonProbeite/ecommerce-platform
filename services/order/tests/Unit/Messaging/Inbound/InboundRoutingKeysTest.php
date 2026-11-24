<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\InboundRoutingKeys;
use App\Infrastructure\Messaging\Topology;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InboundRoutingKeysTest extends TestCase
{
    #[Test]
    public function inbound_routing_keys_match_topology_bindings(): void
    {
        $expected = [
            'product.created',
            'product.updated',
            'product.deleted',
            'stock.reserved',
            'stock.reservation_failed',
        ];

        self::assertSame($expected, InboundRoutingKeys::all());
        self::assertSame('stock.reserved', InboundRoutingKeys::STOCK_RESERVED);
        self::assertSame('stock.reservation_failed', InboundRoutingKeys::STOCK_RESERVATION_FAILED);
        self::assertSame('product.created', InboundRoutingKeys::PRODUCT_CREATED);
        self::assertSame('product.updated', InboundRoutingKeys::PRODUCT_UPDATED);
        self::assertSame('product.deleted', InboundRoutingKeys::PRODUCT_DELETED);
    }

    #[Test]
    public function inbound_routing_keys_match_topology_binding_constants(): void
    {
        self::assertSame(
            InboundRoutingKeys::all(),
            Topology::INBOUND_BINDING_ROUTING_KEYS,
        );
    }
}
