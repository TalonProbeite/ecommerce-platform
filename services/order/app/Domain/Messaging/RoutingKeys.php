<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

final class RoutingKeys
{
    public const ORDER_CREATED = 'order.created';
    public const ORDER_CANCELLED = 'order.cancelled';
    public const ORDER_ISSUED = 'order.issued';
    public const ORDER_RETURNED = 'order.returned';
    public const ORDER_PAID = 'order.paid';

    public const STOCK_RESERVED = 'stock.reserved';
    public const STOCK_RESERVATION_FAILED = 'stock.reservation_failed';
    public const PRODUCT_CREATED = 'product.created';
    public const PRODUCT_UPDATED = 'product.updated';
    public const PRODUCT_DELETED = 'product.deleted';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ...self::outbound(),
            ...self::inbound(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function inbound(): array
    {
        return [
            self::PRODUCT_CREATED,
            self::PRODUCT_UPDATED,
            self::PRODUCT_DELETED,
            self::STOCK_RESERVED,
            self::STOCK_RESERVATION_FAILED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function outbound(): array
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
