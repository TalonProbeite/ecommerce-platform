<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CategoryNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $categoryId = null,
    ) {
        $message = "Category not found!";

        parent::__construct($message);
    }
}
