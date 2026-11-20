<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;

final class AuthenticationTest extends ApiTestCase
{
    #[Test]
    public function it_rejects_a_request_without_a_token(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function it_does_not_authenticate_from_the_authorization_header(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER);

        $this->getJson('/api/v1/orders', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function it_rejects_a_token_with_an_invalid_user_id(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER, userId: 'not-a-uuid');

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(401);
    }

    #[Test]
    public function it_rejects_an_expired_token(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER, expiresAt: time() - 10);

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(401);
    }

    #[Test]
    public function it_rejects_a_token_without_an_expiration(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER, extraClaims: ['exp' => null]);

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(401);
    }

    #[Test]
    public function it_rejects_an_unknown_role(): void
    {
        $token = $this->token([
            'user_id' => self::CUSTOMER_ID,
            'role' => 'root',
            'exp' => time() + 3600,
        ]);

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(401);
    }

    #[Test]
    public function it_rejects_a_token_with_a_bad_signature(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER);
        $segments = explode('.', $token);
        $segments[2] = ($segments[2][0] === 'A' ? 'B' : 'A') . substr($segments[2], 1);

        $this->getJson('/api/v1/orders', $this->headers(implode('.', $segments)))
            ->assertStatus(401);
    }

    #[Test]
    public function it_forbids_a_store_manager_without_store_id(): void
    {
        $token = $this->tokenFor(Role::STORE_MANAGER);

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(403);
    }

    #[Test]
    public function it_treats_a_null_store_id_as_missing(): void
    {
        $token = $this->tokenFor(
            Role::STORE_MANAGER,
            extraClaims: ['store_id' => null],
        );

        $this->getJson('/api/v1/orders', $this->headers($token))
            ->assertStatus(403);
    }
}
