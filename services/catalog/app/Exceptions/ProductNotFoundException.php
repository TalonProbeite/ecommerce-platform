<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $productId = null,
    ) {
        $message = "Product not found!";

        parent::__construct($message);
    }
}
