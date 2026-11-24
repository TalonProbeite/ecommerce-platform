<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Infrastructure\Messaging\Topology;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TopologyContractTest extends TestCase
{
    #[Test]
    public function outbound_exchange_parameters_match_messaging_contract(): void
    {
        // Table in docs/contracts/messaging.md:
        // Exchange: order.events | Type: topic, durable | Declared by: Order
        self::assertSame('order.events', Topology::OUTBOUND_EXCHANGE_NAME);
        self::assertSame('topic', Topology::OUTBOUND_EXCHANGE_TYPE);
        self::assertTrue(Topology::OUTBOUND_EXCHANGE_DURABLE);
        self::assertFalse(Topology::OUTBOUND_EXCHANGE_AUTO_DELETE);
        self::assertFalse(Topology::OUTBOUND_EXCHANGE_INTERNAL);
    }

    #[Test]
    public function inbound_exchange_parameters_match_messaging_contract(): void
    {
        // Table in docs/contracts/messaging.md:
        // Exchange: catalog.events | Type: topic, durable | Declared by: Catalog
        self::assertSame('catalog.events', Topology::INBOUND_EXCHANGE_NAME);
        self::assertSame('topic', Topology::INBOUND_EXCHANGE_TYPE);
        self::assertTrue(Topology::INBOUND_EXCHANGE_DURABLE);
        self::assertFalse(Topology::INBOUND_EXCHANGE_AUTO_DELETE);
        self::assertFalse(Topology::INBOUND_EXCHANGE_INTERNAL);
    }

    #[Test]
    public function inbound_queue_and_dlx_parameters_match_specification(): void
    {
        self::assertSame('order.catalog-events', Topology::INBOUND_QUEUE_NAME);
        self::assertSame('quorum', Topology::INBOUND_QUEUE_TYPE);
        self::assertTrue(Topology::INBOUND_QUEUE_DURABLE);
        self::assertFalse(Topology::INBOUND_QUEUE_AUTO_DELETE);
        self::assertFalse(Topology::INBOUND_QUEUE_EXCLUSIVE);

        self::assertSame('order.catalog-events.dlx', Topology::DLX_EXCHANGE_NAME);
        self::assertSame('direct', Topology::DLX_EXCHANGE_TYPE);
        self::assertTrue(Topology::DLX_EXCHANGE_DURABLE);
        self::assertFalse(Topology::DLX_EXCHANGE_AUTO_DELETE);

        self::assertSame('order.catalog-events.dead', Topology::DEAD_QUEUE_NAME);
        self::assertSame('quorum', Topology::DEAD_QUEUE_TYPE);
        self::assertTrue(Topology::DEAD_QUEUE_DURABLE);

        self::assertSame(5, Topology::DELIVERY_LIMIT);
        self::assertSame('at-least-once', Topology::DEAD_LETTER_STRATEGY);
        self::assertSame('reject-publish', Topology::OVERFLOW);
    }
}
