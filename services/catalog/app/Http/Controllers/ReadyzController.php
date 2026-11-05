<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Health\ReadinessChecker;
use Illuminate\Http\JsonResponse;

final class ReadyzController extends Controller
{
    public function __invoke(ReadinessChecker $checker): JsonResponse
    {
        $results = $checker->check();
        $ready = $checker->isReady($results);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $results,
        ], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
