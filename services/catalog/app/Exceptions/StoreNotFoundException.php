<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class StoreNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $storeId = null,
    ) {
        $message = "Store not found!";

        parent::__construct($message);
    }
}
