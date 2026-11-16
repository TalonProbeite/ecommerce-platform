<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class ListOrdersHttpTest extends ApiTestCase
{
    private const string ORDER_1 = '77777777-7777-4777-8777-777777777771';
    private const string ORDER_2 = '77777777-7777-4777-8777-777777777772';
    private const string ORDER_3 = '77777777-7777-4777-8777-777777777773';
    private const string ORDER_4 = '77777777-7777-4777-8777-777777777774';

    protected function setUp(): void
    {
        parent::setUp();

        $this->readOrders->add($this->seedOrder(
            self::ORDER_1,
            self::CUSTOMER_ID,
            self::STORE_ID,
            OrderStatus::CREATED,
            CarbonImmutable::parse('2026-11-20T10:00:00.000000+00:00'),
        ));

        $this->readOrders->add($this->seedOrder(
            self::ORDER_2,
            self::CUSTOMER_ID,
            self::STORE_ID,
            OrderStatus::PAID,
            CarbonImmutable::parse('2026-11-20T11:00:00.000000+00:00'),
        ));

        $this->readOrders->add($this->seedOrder(
            self::ORDER_3,
            self::OTHER_CUSTOMER_ID,
            self::STORE_ID,
            OrderStatus::CREATED,
            CarbonImmutable::parse('2026-11-20T12:00:00.000000+00:00'),
        ));

        $this->readOrders->add($this->seedOrder(
            self::ORDER_4,
            self::OTHER_CUSTOMER_ID,
            self::OTHER_STORE_ID,
            OrderStatus::CREATED,
            CarbonImmutable::parse('2026-11-20T13:00:00.000000+00:00'),
        ));
    }

    #[Test]
    public function it_lists_only_the_customers_own_orders(): void
    {
        $this->getJson(
            '/api/v1/orders',
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::CUSTOMER_ID)),
        )->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_2)
            ->assertJsonPath('data.1.id', self::ORDER_1);
    }

    #[Test]
    public function it_lists_only_the_managers_store_orders(): void
    {
        $this->getJson(
            '/api/v1/orders',
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID)),
        )->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_3);
    }

    #[Test]
    public function it_lists_all_orders_for_an_admin(): void
    {
        $this->getJson(
            '/api/v1/orders',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function it_lists_all_orders_for_an_analyst(): void
    {
        $this->getJson(
            '/api/v1/orders',
            $this->headers($this->tokenFor(Role::ANALYST)),
        )->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function it_filters_by_store_for_an_admin(): void
    {
        $this->getJson(
            '/api/v1/orders?store_id=' . self::OTHER_STORE_ID,
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_4);
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $this->getJson(
            '/api/v1/orders?status=paid',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_2);
    }

    #[Test]
    public function it_forbids_store_filter_for_a_customer(): void
    {
        $this->getJson(
            '/api/v1/orders?store_id=' . self::OTHER_STORE_ID,
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::CUSTOMER_ID)),
        )->assertStatus(403);
    }

    #[Test]
    public function it_returns_the_next_cursor(): void
    {
        $token = $this->tokenFor(Role::ADMIN);

        $first = $this->getJson('/api/v1/orders?limit=1', $this->headers($token));
        $first->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_4);

        $cursor = $first->json('meta.next_cursor');

        self::assertIsString($cursor);

        $this->getJson(
            '/api/v1/orders?limit=1&cursor=' . urlencode($cursor),
            $this->headers($token),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::ORDER_3);
    }
}
