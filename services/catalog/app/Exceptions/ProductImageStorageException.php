<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
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

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Image storage is temporarily unavailable.',
            'code' => 'product_image_storage_unavailable',
        ], 503);
    }
}
