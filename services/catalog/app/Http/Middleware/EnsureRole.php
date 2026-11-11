<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Ensure the JWT payload contains one of the allowed roles.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $payload = $request->attributes->get('jwt_data');
        $role = is_array($payload) ? ($payload['role'] ?? null) : null;

        if (! is_string($role) || ! in_array($role, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
