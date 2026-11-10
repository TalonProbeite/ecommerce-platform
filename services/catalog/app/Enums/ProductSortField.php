<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductSortField: string
{
    case PRICE = 'price_per_hour';
    case CREATED_AT = 'created_at';
}
