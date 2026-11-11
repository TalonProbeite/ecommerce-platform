<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct("Insufficient stock for product id={$this->productId}.");
    }
}
