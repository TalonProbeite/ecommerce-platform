<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnsupportedImageTypeException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly ?string $mimeType = null,
    ) {
        $message = "File type {$this->mimeType}, is not supported!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'details' => [
                'mime_type' => $this->mimeType,
            ],
        ], 422);
    }
}
