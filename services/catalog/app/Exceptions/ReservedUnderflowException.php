<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ReservedUnderflowException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
        public readonly int $reserved,
        public readonly int $releaseAmount,
    ) {
        parent::__construct("Cannot release more units than reserved!");
    }
}
