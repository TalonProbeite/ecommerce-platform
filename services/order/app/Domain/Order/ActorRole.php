<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Enums\Role;

enum ActorRole: string
{
    case CUSTOMER = 'customer';
    case STORE_MANAGER = 'store_manager';
    case ADMIN = 'admin';
    case ANALYST = 'analyst';
    case SYSTEM = 'system';
    case STRIPE = 'stripe';

    public static function fromRole(Role $role): self
    {
        return self::from($role->value);
    }
}
