<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound;

final class InboundRoutingKeys
{
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
            self::STOCK_RESERVED,
            self::STOCK_RESERVATION_FAILED,
            self::PRODUCT_CREATED,
            self::PRODUCT_UPDATED,
            self::PRODUCT_DELETED,
        ];
    }

    private function __construct()
    {
    }
}
