<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ProductNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $productId = null,
    ) {
        $message = "Product not found!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'details' => [
                'product' => $this->productId,
            ],
        ], 404);
    }
}
