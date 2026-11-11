<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class QuantityBelowReservedException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
        public readonly int $reserved,
        public readonly int $requested,
    ) {
        parent::__construct("Quantity cannot be below reserved amount.");
    }
}
