<?php

namespace App\Http\Middleware;

use Closure;
use App\Enums\Role;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureAdminOrStoreManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(
                ['message' => 'Unauthorized. Token not provided.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $b64Key = config('jwt.public_key_b64');

        if (! is_string($b64Key) || $b64Key === '') {
            return response()->json(
                ['message' => 'Internal server error.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $publicKey = base64_decode($b64Key, true);

        if ($publicKey === false) {
            return response()->json(
                ['message' => 'Internal server error.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        try {
            $algo = config('jwt.algo', 'RS256');
            $decoded = JWT::decode($token, new Key($publicKey, $algo));

            $payload = (array) $decoded;
            $role = Role::tryFrom($payload['role'] ?? null);

            if (! in_array($role, [Role::ADMIN, Role::STORE_MANAGER], true)) {
                return response()->json(
                    ['message' => 'Forbidden. Insufficient permissions.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($role === Role::STORE_MANAGER && empty($payload['store_id'])) {
                return response()->json(
                    ['message' => 'Forbidden. Store context is missing.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            $request->attributes->add(['jwt_data' => $payload]);

        } catch (Throwable $e) {
            return response()->json(
                ['message' => 'Unauthorized. Invalid token.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
