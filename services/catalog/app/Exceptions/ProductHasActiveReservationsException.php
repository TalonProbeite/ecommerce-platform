<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductHasActiveReservationsException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $productId = null,
    ) {
        $message = "Cannot delete the product id={$this->productId}, because it has active reservations!";

        parent::__construct($message);
    }
}
