<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Routing;

final class OutboundRoutingKeys
{
    public const ORDER_CREATED = 'order.created';
    public const ORDER_CANCELLED = 'order.cancelled';
    public const ORDER_ISSUED = 'order.issued';
    public const ORDER_RETURNED = 'order.returned';
    public const ORDER_PAID = 'order.paid';

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
            self::ORDER_PAID,
        ];
    }

    private function __construct()
    {
    }
}
