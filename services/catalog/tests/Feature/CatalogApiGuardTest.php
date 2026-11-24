<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogApiGuardTest extends TestCase
{
    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    private const string ADMIN_PATH = '/api/v1/admin/stores/' . self::STORE_ID . '/products';

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));

        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);

        $this->privateKey = $privateKey;

        config([
            'jwt.public_key_b64' => base64_encode($details['key']),
            'jwt.algo' => 'RS256',
            'jwt.issuer' => null,
            'jwt.audience' => null,
        ]);
    }

    #[Test]
    public function health_endpoint_is_public(): void
    {
        $this->get('/healthz')->assertOk();
    }

    #[Test]
    public function admin_routes_require_a_token(): void
    {
        $this->getJson(self::ADMIN_PATH)
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized. Token not provided.');
    }

    #[Test]
    public function admin_routes_reject_an_invalid_token(): void
    {
        $this->getJson(self::ADMIN_PATH, ['Authorization' => 'Bearer not-a-token'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized. Invalid token.');
    }

    #[Test]
    public function admin_routes_reject_a_customer(): void
    {
        $this->getJson(self::ADMIN_PATH, ['Authorization' => 'Bearer ' . $this->token(Role::CUSTOMER)])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Insufficient permissions.');
    }

    #[Test]
    public function admin_routes_reject_an_analyst(): void
    {
        $this->postJson('/api/v1/admin/stores', ['name' => 'Tools'], [
            'Authorization' => 'Bearer ' . $this->token(Role::ANALYST),
        ])->assertStatus(403);
    }

    #[Test]
    public function admin_routes_reject_a_store_manager_without_a_store(): void
    {
        $this->getJson(self::ADMIN_PATH, ['Authorization' => 'Bearer ' . $this->token(Role::STORE_MANAGER)])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Store context is missing.');
    }

    private function token(Role $role, ?string $storeId = null): string
    {
        $now = time();

        $claims = [
            'iat' => $now,
            'exp' => $now + 3600,
            'user_id' => '11111111-1111-4111-8111-999999999999',
            'role' => $role->value,
        ];

        if ($storeId !== null) {
            $claims['store_id'] = $storeId;
        }

        return JWT::encode($claims, $this->privateKey, 'RS256');
    }
}
