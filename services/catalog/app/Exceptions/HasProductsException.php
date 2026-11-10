<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class HasProductsException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $categoryId,
    ) {
        $message = "Cannot delete the category id={$this->categoryId}, because it has products!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => $this->getMessage(),
            'details' => [
                'category' => $this->categoryId,
            ]
        ], 409);
    }
}
