<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\Order\Order;
use Carbon\CarbonImmutable;

interface OrderRepository
{
    public function findById(string $id): ?Order;

    /**
     * Load an order inside the current transaction and lock its row
     * (SELECT ... FOR UPDATE). Must be called from inside a transaction.
     */
    public function findByIdForUpdate(string $id): ?Order;

    /**
     * Load unpaid orders created before the cutoff and lock their rows
     * (SELECT ... FOR UPDATE SKIP LOCKED). Must be called from inside a
     * transaction.
     *
     * @return list<Order>
     */
    public function lockExpiredUnpaidForUpdate(CarbonImmutable $createdBefore, int $limit): array;

    public function findByIdempotencyKey(string $customerId, string $key): ?Order;

    public function save(Order $order): void;
}
