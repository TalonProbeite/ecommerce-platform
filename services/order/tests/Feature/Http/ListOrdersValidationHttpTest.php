<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;

final class ListOrdersValidationHttpTest extends ApiTestCase
{
    #[Test]
    public function it_rejects_an_unknown_status(): void
    {
        $this->getJson(
            '/api/v1/orders?status=unknown',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function it_rejects_a_malformed_store_id(): void
    {
        $this->getJson(
            '/api/v1/orders?store_id=not-a-uuid',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    #[Test]
    public function it_rejects_a_limit_below_the_minimum(): void
    {
        $this->getJson(
            '/api/v1/orders?limit=0',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    #[Test]
    public function it_rejects_a_limit_above_the_maximum(): void
    {
        $this->getJson(
            '/api/v1/orders?limit=101',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    #[Test]
    public function it_rejects_a_non_integer_limit(): void
    {
        $this->getJson(
            '/api/v1/orders?limit=ten',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }
}
