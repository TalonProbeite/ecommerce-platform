<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\IdempotencyLock;

final class FakeIdempotencyLock implements IdempotencyLock
{
    /**
     * @var array<string, int>
     */
    public array $acquired = [];

    /**
     * @var list<string>
     */
    public array $released = [];

    /**
     * @var array<string, string>
     */
    public array $tokens = [];

    public bool $denyAcquire = false;

    public function acquire(string $key, string $token, int $ttlSeconds): bool
    {
        if ($this->denyAcquire || isset($this->tokens[$key])) {
            return false;
        }

        $this->acquired[$key] = $ttlSeconds;
        $this->tokens[$key] = $token;

        return true;
    }

    public function release(string $key, string $token): void
    {
        if (($this->tokens[$key] ?? null) !== $token) {
            return;
        }

        unset($this->tokens[$key]);
        $this->released[] = $key;
    }

    public function wasReleased(string $key): bool
    {
        return in_array($key, $this->released, true);
    }

    public function isHeld(string $key): bool
    {
        return isset($this->tokens[$key]);
    }

    public function tokenFor(string $key): ?string
    {
        return $this->tokens[$key] ?? null;
    }
}
