<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Order\Order;
use App\Enums\Role;
use App\Http\Auth\AuthUser;

final class OrderPolicy
{
    public function view(AuthUser $user, Order $order): bool
    {
        return match ($user->role) {
            Role::CUSTOMER => $order->customerId() === $user->id,
            Role::STORE_MANAGER => $user->storeId !== null && $order->storeId() === $user->storeId,
            Role::ADMIN, Role::ANALYST => true,
        };
    }

    public function create(AuthUser $user): bool
    {
        return $user->role === Role::CUSTOMER;
    }
}
