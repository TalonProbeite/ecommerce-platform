<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class HasProductsException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $categoryId,
    ) {
        $message = "Cannot delete the category id={$this->categoryId}, because it has products!";

        parent::__construct($message);
    }
}
