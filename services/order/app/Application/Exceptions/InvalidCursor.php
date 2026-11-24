<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class InvalidCursor extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The cursor is malformed.');
    }
}
