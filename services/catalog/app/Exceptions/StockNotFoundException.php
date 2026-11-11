<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class StockNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
    ) {
        parent::__construct("Stock not found for product id={$this->productId}.");
    }
}
