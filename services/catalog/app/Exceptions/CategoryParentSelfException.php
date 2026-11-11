<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CategoryParentSelfException extends Exception
{
    /**
     * @var array<string, array<int, string>>
     */
    public readonly array $errors;

    public function __construct(string $field, string $message)
    {
        parent::__construct($message);

        $this->errors = [$field => [$message]];
    }

    public function report(): bool
    {
        return false;
    }
}
