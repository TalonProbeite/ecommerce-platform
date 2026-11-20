<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Http\Responses\ProblemResponse;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class Authenticate
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token');

        if (! is_string($token) || $token === '') {
            return ProblemResponse::make(
                Response::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'An access_token cookie is required.',
            );
        }

        $publicKey = $this->publicKey();

        if ($publicKey === null) {
            return ProblemResponse::make(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Internal Server Error',
                'The JWT public key is not configured.',
            );
        }

        $payload = $this->decode($token, $publicKey);

        if ($payload === null) {
            return ProblemResponse::make(
                Response::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'The token is invalid or expired.',
            );
        }

        $userId = $payload['user_id'] ?? null;

        if (! is_string($userId) || ! Str::isUuid($userId)) {
            return ProblemResponse::make(
                Response::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'The user_id claim must be a valid UUID.',
            );
        }

        $rawRole = $payload['role'] ?? null;
        $role = is_string($rawRole) ? Role::tryFrom($rawRole) : null;

        if ($role === null) {
            return ProblemResponse::make(
                Response::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'The role claim is missing or unknown.',
            );
        }

        $rawStoreId = $payload['store_id'] ?? null;
        $storeId = is_string($rawStoreId) && $rawStoreId !== '' ? $rawStoreId : null;

        if ($role === Role::STORE_MANAGER && $storeId === null) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'A store_manager token must contain store_id.',
            );
        }

        $request->attributes->set(
            AuthUser::ATTRIBUTE,
            new AuthUser($userId, $role, $storeId),
        );

        return $next($request);
    }

    private function publicKey(): ?string
    {
        $encoded = config('jwt.public_key_b64');

        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        return @openssl_pkey_get_public($decoded) === false ? null : $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $token, string $publicKey): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (Throwable) {
            return null;
        }

        $payload = (array) $decoded;
        $expiresAt = $payload['exp'] ?? null;

        if (! is_int($expiresAt) && ! is_float($expiresAt)) {
            return null;
        }

        if ((int) $expiresAt <= time()) {
            return null;
        }

        return $payload;
    }
}
