<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class ForbiddenException extends RuntimeException
{
    public function __construct(string $message = 'The authenticated user is not allowed to perform this action.')
    {
        parent::__construct($message);
    }
}
