<?php

namespace App\Http\Middleware;

use Closure;
use App\Enums\Role;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $algo = config('jwt.algo', 'RS256');
        $decoded = rescue(
            static fn (): object => JWT::decode($token, new Key($publicKey, $algo)),
            rescue: null,
            report: false,
        );

        if (! is_object($decoded)) {
            return response()->json(
                ['message' => 'Unauthorized. Invalid token.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $payload = (array) $decoded;

        if (! isset($payload['exp'])) {
            return response()->json(
                ['message' => 'Unauthorized. Invalid token.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $issuer = config('jwt.issuer');
        if (is_string($issuer) && $issuer !== '') {
            if (! isset($payload['iss']) || $payload['iss'] !== $issuer) {
                return response()->json(
                    ['message' => 'Unauthorized. Invalid token.'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        $audience = config('jwt.audience');
        if (is_string($audience) && $audience !== '') {
            $aud = $payload['aud'] ?? null;
            $audValid = is_array($aud)
                ? in_array($audience, $aud, true)
                : $aud === $audience;

            if (! $audValid) {
                return response()->json(
                    ['message' => 'Unauthorized. Invalid token.'],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        $rawRole = $payload['role'] ?? null;
        $role = is_string($rawRole) ? Role::tryFrom($rawRole) : null;

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

        return $next($request);
    }
}
