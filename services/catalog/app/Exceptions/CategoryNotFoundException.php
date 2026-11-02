<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CategoryNotFoundException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $categoryId = null,
    ) {
        $message = "Category nit found!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'details' => [
                'category' => $this->categoryId,
            ],
        ], 404);
    }
}
