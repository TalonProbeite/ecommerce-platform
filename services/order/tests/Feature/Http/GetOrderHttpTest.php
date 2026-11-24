<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;

final class GetOrderHttpTest extends ApiTestCase
{
    private const string ORDER_ID = '55555555-5555-4555-8555-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistNewOrder(self::ORDER_ID, self::CUSTOMER_ID, self::STORE_ID);
    }

    #[Test]
    public function it_allows_the_customer_owner_to_view_an_order(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::CUSTOMER_ID)),
        )->assertStatus(200)
            ->assertJsonPath('data.id', self::ORDER_ID);
    }

    #[Test]
    public function it_returns_404_for_another_customer(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::OTHER_CUSTOMER_ID)),
        )->assertStatus(404);
    }

    #[Test]
    public function it_allows_a_store_manager_from_the_same_store(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID)),
        )->assertStatus(200);
    }

    #[Test]
    public function it_returns_404_for_a_store_manager_from_another_store(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::OTHER_STORE_ID)),
        )->assertStatus(404);
    }

    #[Test]
    public function it_allows_an_admin_to_view_any_order(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200);
    }

    #[Test]
    public function it_allows_an_analyst_to_view_any_order(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID,
            $this->headers($this->tokenFor(Role::ANALYST)),
        )->assertStatus(200);
    }

    #[Test]
    public function it_returns_404_for_a_missing_order(): void
    {
        $this->getJson(
            '/api/v1/orders/66666666-6666-4666-8666-666666666666',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(404);
    }
}
