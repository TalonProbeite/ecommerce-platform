<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductImageNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $productImageId = null,
    ) {
        $message = "Product image not found!";

        parent::__construct($message);
    }
}
