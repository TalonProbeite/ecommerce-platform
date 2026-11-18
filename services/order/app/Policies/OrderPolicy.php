<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Ports\AuthenticatedUser;
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

    public function viewOverdue(AuthenticatedUser $user): bool
    {
        return match ($user->role()) {
            Role::ADMIN, Role::ANALYST => true,
            default => false,
        };
    }

    public function create(AuthUser $user): bool
    {
        return $user->role === Role::CUSTOMER;
    }

    public function pay(AuthUser $user, Order $order): bool
    {
        return $user->role === Role::CUSTOMER && $order->customerId() === $user->id;
    }

    public function cancel(AuthUser $user, Order $order): bool
    {
        return match ($user->role) {
            Role::CUSTOMER => $order->customerId() === $user->id,
            Role::STORE_MANAGER => $user->storeId !== null && $order->storeId() === $user->storeId,
            Role::ADMIN => true,
            Role::ANALYST => false,
        };
    }

    public function issue(AuthUser $user, Order $order): bool
    {
        return $this->manage($user, $order);
    }

    public function return(AuthUser $user, Order $order): bool
    {
        return $this->manage($user, $order);
    }

    private function manage(AuthUser $user, Order $order): bool
    {
        return match ($user->role) {
            Role::STORE_MANAGER => $user->storeId !== null && $order->storeId() === $user->storeId,
            Role::ADMIN => true,
            default => false,
        };
    }
}
