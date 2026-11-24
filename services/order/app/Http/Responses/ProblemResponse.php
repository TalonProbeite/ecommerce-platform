<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ProblemResponse
{
    /**
     * @param array<string, mixed> $extra
     */
    public static function make(int $status, string $title, string $detail, array $extra = []): JsonResponse
    {
        return new JsonResponse(
            array_merge([
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
            ], $extra),
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    private function __construct()
    {
    }
}
