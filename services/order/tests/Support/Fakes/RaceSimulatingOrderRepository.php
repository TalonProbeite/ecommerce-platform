<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Exceptions\IdempotencyKeyAlreadyExists;
use App\Application\Ports\OrderRepository;
use App\Domain\Order\Order;

final class RaceSimulatingOrderRepository implements OrderRepository
{
    private int $lookups = 0;

    public function __construct(private readonly Order $existing)
    {
    }

    public function findById(string $id): ?Order
    {
        return $id === $this->existing->id() ? $this->existing->copy() : null;
    }

    public function findByIdempotencyKey(string $customerId, string $key): ?Order
    {
        $this->lookups++;

        if ($this->lookups === 1) {
            return null;
        }

        return $this->existing->customerId() === $customerId
            && $this->existing->idempotencyKey() === $key
            ? $this->existing->copy()
            : null;
    }

    public function save(Order $order): void
    {
        throw new IdempotencyKeyAlreadyExists($order->customerId(), $order->idempotencyKey());
    }
}
