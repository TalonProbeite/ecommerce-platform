<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Order\Order;

interface OrderRepository
{
    public function findById(string $id): ?Order;

    /**
     * Load an order inside the current transaction and lock its row
     * (SELECT ... FOR UPDATE). Must be called from inside a transaction.
     */
    public function findByIdForUpdate(string $id): ?Order;

    public function findByIdempotencyKey(string $customerId, string $key): ?Order;

    public function save(Order $order): void;
}
