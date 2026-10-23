<?php

declare(strict_types=1);

namespace App\Messaging;

final class RoutingKeys
{
    public const PRODUCT_CREATED = 'product.created';
    public const PRODUCT_UPDATED = 'product.updated';
    public const PRODUCT_DELETED = 'product.deleted';

    public const CATEGORY_CREATED = 'category.created';
    public const CATEGORY_UPDATED = 'category.updated';
    public const CATEGORY_DELETED = 'category.deleted';

    public const STOCK_CHANGED = 'stock.changed';
    public const STOCK_RESERVED = 'stock.reserved';
    public const STOCK_RESERVATION_FAILED = 'stock.reservation_failed';
}
