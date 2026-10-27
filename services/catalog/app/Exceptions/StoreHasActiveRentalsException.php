<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class StoreHasActiveRentalsException extends Exception
{
    public function report(): bool
    {
        return false;
    }

    public function __construct(
        public readonly string $storeID,
    ) {
        $message = "Unable to delete store id={$this->storeID}, there are active reservations!";

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => $this->getMessage(),
            'details' => [
                'store' => $this->storeID,
            ]
        ], 409);
    }
}
