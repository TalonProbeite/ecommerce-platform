<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class InvalidOrderItems extends DomainException
{
    public static function empty(): self
    {
        return new self('An order must contain at least one item.');
    }

    public static function tooMany(int $count): self
    {
        return new self(sprintf('An order cannot contain more than 100 items, got %d.', $count));
    }

    public static function mixedStore(): self
    {
        return new self('All order items must belong to the same store.');
    }
}
