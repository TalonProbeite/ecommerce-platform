<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StockConcurrencyException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
    ) {
        parent::__construct("Stock was modified concurrently. Please retry.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => $this->getMessage(),
            'details' => [
                'product'   => $this->productId,
            ],
        ], 409);
    }
}