<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class ProductImageStorageException extends Exception
{
    public function report(): bool
    {
        Log::error('Product image storage failed', ['object_key' => $this->objectKey]);

        return false;
    }

    public function __construct(
        public readonly ?string $objectKey = null,
    ) {
        $message = "Failed to save product image!";

        parent::__construct($message);
    }
}
