<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Role;

/**
 * Minimal RS256 JWT helpers for HTTP feature tests that need a real database
 * and therefore cannot extend Tests\Feature\Http\ApiTestCase.
 */
trait JwtAuth
{
    private \OpenSSLAsymmetricKey $jwtPrivateKey;

    protected function setUpJwtKeys(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);

        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        $this->jwtPrivateKey = $privateKey;

        config([
            'jwt.public_key_b64' => base64_encode($details['key']),
            'jwt.algo' => 'RS256',
        ]);
    }

    protected function tokenFor(Role $role, ?string $storeId = null, ?string $userId = null): string
    {
        $claims = [
            'user_id' => $userId ?? '11111111-1111-4111-8111-999999999999',
            'role' => $role->value,
        ];

        if ($storeId !== null) {
            $claims['store_id'] = $storeId;
        }

        $now = time();

        $header = $this->encodeJwtPart(['alg' => 'RS256', 'typ' => 'JWT']);
        $body = $this->encodeJwtPart(array_merge([
            'iat' => $now,
            'exp' => $now + 3600,
        ], $claims));
        $signature = '';

        openssl_sign($header . '.' . $body, $signature, $this->jwtPrivateKey, OPENSSL_ALGO_SHA256);

        return $header . '.' . $body . '.' . $this->encodeJwtBytes($signature);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJwtPart(array $value): string
    {
        return $this->encodeJwtBytes(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function encodeJwtBytes(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
