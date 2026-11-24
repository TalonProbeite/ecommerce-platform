<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Ports\Clock;
use App\Enums\Role;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\PostgresTestCase;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\JwtAuth;

final class OverdueRentalsHttpTest extends PostgresTestCase
{
    use JwtAuth;

    private const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';

    private const string MANAGER_ID = '99999999-9999-4999-8999-999999999999';

    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    private const string OTHER_STORE_ID = '22222222-2222-4222-8222-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpJwtKeys();
        $this->app->instance(Clock::class, new FixedClock(
            CarbonImmutable::parse('2026-12-02T12:00:00.000000+00:00'),
        ));
    }

    #[Test]
    public function it_lists_overdue_rentals_for_an_admin(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00');

        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_id', self::CUSTOMER_ID)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.next_cursor', null);
    }

    #[Test]
    public function it_lists_overdue_rentals_for_an_analyst(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00');

        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::ANALYST)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Drill')
            ->assertJsonPath('data.0.quantity', 1)
            ->assertJsonPath('data.0.rental_end_at', '2026-12-02T10:00:00.000000+00:00')
            ->assertJsonPath('data.0.overdue_hours', 2);
    }

    #[Test]
    public function it_exposes_the_documented_fields(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00');

        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'order_id',
                    'item_id',
                    'product_id',
                    'product_name',
                    'store_id',
                    'customer_id',
                    'quantity',
                    'rental_end_at',
                    'overdue_hours',
                ]],
                'meta' => ['next_cursor', 'count'],
            ]);
    }

    #[Test]
    public function it_forbids_a_customer(): void
    {
        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::CUSTOMER_ID)),
        )->assertStatus(403);
    }

    #[Test]
    public function it_forbids_a_store_manager(): void
    {
        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID, self::MANAGER_ID)),
        )->assertStatus(403);
    }

    #[Test]
    public function it_requires_a_token(): void
    {
        $this->getJson('/api/v1/analytics/overdue-rentals')->assertStatus(401);
    }

    #[Test]
    public function it_returns_only_issued_items_that_are_not_returned(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00', 'issued');
        $this->seedItem('2026-12-02T10:05:00.000000+00:00', 'returned');
        $this->seedItem('2026-12-02T10:10:00.000000+00:00', 'reserved');

        $this->getJson(
            '/api/v1/analytics/overdue-rentals',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rental_end_at', '2026-12-02T10:00:00.000000+00:00');
    }

    #[Test]
    public function it_filters_by_store(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00', 'issued', self::STORE_ID);
        $this->seedItem('2026-12-02T10:30:00.000000+00:00', 'issued', self::OTHER_STORE_ID);

        $this->getJson(
            '/api/v1/analytics/overdue-rentals?store_id=' . self::OTHER_STORE_ID,
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.store_id', self::OTHER_STORE_ID);
    }

    #[Test]
    public function it_paginates_with_a_cursor(): void
    {
        $this->seedItem('2026-12-02T10:00:00.000000+00:00');
        $this->seedItem('2026-12-02T11:00:00.000000+00:00');

        $token = $this->tokenFor(Role::ADMIN);

        $first = $this->getJson(
            '/api/v1/analytics/overdue-rentals?limit=1',
            $this->headers($token),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rental_end_at', '2026-12-02T10:00:00.000000+00:00');

        $cursor = $first->json('meta.next_cursor');

        self::assertIsString($cursor);

        $this->getJson(
            '/api/v1/analytics/overdue-rentals?limit=1&cursor=' . urlencode($cursor),
            $this->headers($token),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rental_end_at', '2026-12-02T11:00:00.000000+00:00')
            ->assertJsonPath('meta.next_cursor', null);
    }

    #[Test]
    public function it_rejects_an_invalid_cursor(): void
    {
        $response = $this->getJson(
            '/api/v1/analytics/overdue-rentals?cursor=not-a-cursor',
            $this->headers($this->tokenFor(Role::ADMIN)),
        );

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('title', 'Unprocessable Entity');
    }

    #[Test]
    public function it_rejects_a_limit_above_the_maximum(): void
    {
        $this->getJson(
            '/api/v1/analytics/overdue-rentals?limit=101',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    private function seedItem(
        string $rentalEndAt,
        string $itemStatus = 'issued',
        string $storeId = self::STORE_ID,
    ): OrderItemModel {
        $order = $this->newOrder([
            'customer_id' => self::CUSTOMER_ID,
            'store_id' => $storeId,
        ]);

        $overrides = [
            'rental_start_at' => CarbonImmutable::parse($rentalEndAt)->subHours(2),
            'rental_end_at' => CarbonImmutable::parse($rentalEndAt),
            'item_status' => $itemStatus,
        ];

        if ($itemStatus === 'issued' || $itemStatus === 'returned') {
            $overrides['issued_at'] = CarbonImmutable::parse('2026-12-02T08:00:00.000000+00:00');
            $overrides['issued_by'] = self::MANAGER_ID;
        }

        if ($itemStatus === 'returned') {
            $overrides['returned_at'] = CarbonImmutable::parse('2026-12-02T09:00:00.000000+00:00');
            $overrides['returned_by'] = self::MANAGER_ID;
        }

        return $this->newOrderItem($order, $overrides);
    }
}
