<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Routing;

use App\Domain\Messaging\RoutingKeys;

final class OutboundRoutingKeys
{
    public const ORDER_CREATED = RoutingKeys::ORDER_CREATED;
    public const ORDER_CANCELLED = RoutingKeys::ORDER_CANCELLED;
    public const ORDER_ISSUED = RoutingKeys::ORDER_ISSUED;
    public const ORDER_RETURNED = RoutingKeys::ORDER_RETURNED;
    public const ORDER_PAID = RoutingKeys::ORDER_PAID;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return RoutingKeys::outbound();
    }

    private function __construct()
    {
    }
}
