<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Order\Order;

interface OrderRepository
{
    public function findById(string $id): ?Order;

    public function findByIdempotencyKey(string $customerId, string $key): ?Order;

    public function save(Order $order): void;
}
