<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface IdempotencyLock
{
    /**
     * Acquire an exclusive lock for the given key.
     *
     * @param int $ttlSeconds Lifetime in seconds.
     */
    public function acquire(string $key, string $token, int $ttlSeconds): bool;

    public function release(string $key, string $token): void;
}
