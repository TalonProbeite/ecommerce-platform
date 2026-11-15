<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case STORE_MANAGER = 'store_manager';
    case CUSTOMER = 'customer';
    case ANALYST = 'analyst';
}
