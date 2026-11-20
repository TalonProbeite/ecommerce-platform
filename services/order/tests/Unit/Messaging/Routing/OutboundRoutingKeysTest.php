<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Routing;

use App\Infrastructure\Messaging\Routing\OutboundRoutingKeys;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutboundRoutingKeysTest extends TestCase
{
    #[Test]
    public function outbound_routing_keys_match_messaging_contract(): void
    {
        $expected = [
            'order.created',
            'order.cancelled',
            'order.issued',
            'order.returned',
            'order.paid',
        ];

        self::assertSame($expected, OutboundRoutingKeys::all());
        self::assertSame('order.created', OutboundRoutingKeys::ORDER_CREATED);
        self::assertSame('order.cancelled', OutboundRoutingKeys::ORDER_CANCELLED);
        self::assertSame('order.issued', OutboundRoutingKeys::ORDER_ISSUED);
        self::assertSame('order.returned', OutboundRoutingKeys::ORDER_RETURNED);
        self::assertSame('order.paid', OutboundRoutingKeys::ORDER_PAID);
    }
}
