<?php

declare(strict_types=1);

namespace App\Services\Inbound\Results;

enum StockRejectionReason: string
{
    case InsufficientStock = 'insufficient_stock';
    case ProductNotFound = 'product_not_found';
}
