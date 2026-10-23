<?php

declare(strict_types=1);

namespace App\Messaging\Events;

enum StockReservationFailureReason: string
{
    case InsufficientStock = 'insufficient_stock';
    case ProductNotFound = 'product_not_found';
}
