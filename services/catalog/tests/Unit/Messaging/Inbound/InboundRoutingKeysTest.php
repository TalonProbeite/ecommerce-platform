<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Messaging\Inbound\InboundRoutingKeys;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InboundRoutingKeysTest extends TestCase
{
    #[Test]
    public function it_exposes_all_consumed_routing_keys_in_order(): void
    {
        self::assertSame(
            [
                'order.created',
                'order.cancelled',
                'order.issued',
                'order.returned',
            ],
            InboundRoutingKeys::all(),
        );
    }

    #[Test]
    public function it_does_not_consume_order_completed(): void
    {
        self::assertNotContains('order.completed', InboundRoutingKeys::all());
        self::assertCount(4, InboundRoutingKeys::all());
    }
}
