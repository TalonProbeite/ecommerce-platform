<?php

declare(strict_types=1);

namespace App\Domain\Order;

enum OrderStatus: string
{
    case CREATED = 'created';
    case STOCK_RESERVED = 'stock_reserved';
    case PAYMENT_PENDING = 'payment_pending';
    case PAID = 'paid';
    case IN_RENTAL = 'in_rental';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
