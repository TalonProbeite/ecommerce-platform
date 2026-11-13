<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

/**
 * Routing keys for events consumed by Catalog from Order.
 *
 * Kept separate from App\Messaging\RoutingKeys, which holds outbound keys.
 */
final class InboundRoutingKeys
{
    public const ORDER_CREATED = 'order.created';
    public const ORDER_CANCELLED = 'order.cancelled';
    public const ORDER_ISSUED = 'order.issued';
    public const ORDER_RETURNED = 'order.returned';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ORDER_CREATED,
            self::ORDER_CANCELLED,
            self::ORDER_ISSUED,
            self::ORDER_RETURNED,
        ];
    }

    private function __construct()
    {
    }
}
