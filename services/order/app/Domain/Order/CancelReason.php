<?php

declare(strict_types=1);

namespace App\Domain\Order;

enum CancelReason: string
{
    case STOCK_UNAVAILABLE = 'stock_unavailable';
    case PAYMENT_TIMEOUT = 'payment_timeout';
    case CUSTOMER_REQUEST = 'customer_request';
    case ADMIN_REQUEST = 'admin_request';
}
