<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

final readonly class Money
{
    public const string CURRENCY = 'EUR';

    public function __construct(private int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromMinorUnits(int $amount): self
    {
        return new self($amount);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return self::CURRENCY;
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Money multiplier cannot be negative.');
        }

        return new self($this->amount * $factor);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }
}
