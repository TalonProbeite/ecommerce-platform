<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TooManyProductImagesException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $productId = null,
        public readonly ?int $limit = null,
    ) {
        $message = "Too many images!";

        parent::__construct($message);
    }
}
