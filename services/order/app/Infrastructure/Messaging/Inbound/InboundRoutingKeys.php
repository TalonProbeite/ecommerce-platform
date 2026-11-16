<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound;

use App\Domain\Messaging\RoutingKeys;

final class InboundRoutingKeys
{
    public const STOCK_RESERVED = RoutingKeys::STOCK_RESERVED;
    public const STOCK_RESERVATION_FAILED = RoutingKeys::STOCK_RESERVATION_FAILED;
    public const PRODUCT_CREATED = RoutingKeys::PRODUCT_CREATED;
    public const PRODUCT_UPDATED = RoutingKeys::PRODUCT_UPDATED;
    public const PRODUCT_DELETED = RoutingKeys::PRODUCT_DELETED;

    /**
     * Routing keys that have registered handlers in Order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return RoutingKeys::inbound();
    }

    private function __construct()
    {
    }
}
