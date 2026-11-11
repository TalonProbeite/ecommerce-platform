<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class UnsupportedImageTypeException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $mimeType = null,
    ) {
        $message = "File type {$this->mimeType}, is not supported!";

        parent::__construct($message);
    }
}
