<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class StockConcurrencyException extends Exception
{
    public function report(): bool
    {
        return true;
    }

    public function __construct(
        public readonly string $productId,
    ) {
        parent::__construct("Stock was modified concurrently. Please retry.");
    }
}
