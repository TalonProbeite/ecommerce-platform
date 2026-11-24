<?php

declare(strict_types=1);

namespace App\Domain\Order;

enum ItemStatus: string
{
    case RESERVED = 'reserved';
    case ISSUED = 'issued';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
}
