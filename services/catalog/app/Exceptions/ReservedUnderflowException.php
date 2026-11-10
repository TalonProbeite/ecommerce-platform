<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

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

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => $this->getMessage(),
            'details' => [
                'product'   => $this->productId,
                'reserved' => $this->reserved,
                'release_amount' => $this->releaseAmount,
            ],
        ], 409);
    }
}
