<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ProductImageStorageException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $objectKey = null,
    ) {
        $message = "Failed to save product image!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'details' => [
                'product' => $this->objectKey,
            ],
        ], 503);
    }
}
