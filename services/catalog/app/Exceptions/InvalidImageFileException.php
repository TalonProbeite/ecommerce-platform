<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidImageFileException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $reason = null,
    ) {
        $message = "Error loading file, reason: {$this->reason}";

        parent::__construct($message);
    }
}
