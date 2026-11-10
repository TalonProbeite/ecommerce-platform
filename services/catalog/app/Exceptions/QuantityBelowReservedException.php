<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class QuantityBelowReservedException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $productId,
        public readonly int $reserved,
        public readonly int $requested,
    ) {
        parent::__construct("Quantity cannot be below reserved amount.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => $this->getMessage(),
            'details' => [
                'product'   => $this->productId,
                'reserved' => $this->reserved,
                'requested' => $this->requested,
            ],
        ], 422);
    }
}
