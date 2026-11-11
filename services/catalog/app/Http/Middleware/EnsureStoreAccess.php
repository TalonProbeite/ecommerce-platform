<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreAccess
{
    /**
     * Ensure the requested store is accessible to the authenticated role.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestedStoreId = $request->route('storeId');

        if ($requestedStoreId === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $payload = $request->attributes->get('jwt_data');

        if (! is_array($payload)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $role = $payload['role'] ?? null;

        if ($role === Role::ADMIN->value) {
            return $next($request);
        }

        if ($role !== Role::STORE_MANAGER->value) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $storeId = $payload['store_id'] ?? null;

        if (! is_string($storeId) || $storeId === '' || $storeId !== $requestedStoreId) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
