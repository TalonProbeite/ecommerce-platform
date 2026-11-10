<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

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

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'details' => [
                'product' => $this->productId,
                'limit' => $this->limit,
            ],
        ], 422);
    }
}
