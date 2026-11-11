<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ProductCategoryNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $categoryId = null,
    ) {
        $message = 'The selected category does not exist or is not active in this store.';

        parent::__construct($message);
    }
}
