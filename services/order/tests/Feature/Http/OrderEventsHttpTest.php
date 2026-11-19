<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Ports\OrderEventReadRepository;
use App\Application\Query\ListOrderEvents\OrderEventItem;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\InMemoryOrderEventReadRepository;

final class OrderEventsHttpTest extends ApiTestCase
{
    private const string ORDER_ID = '77777777-7777-4777-8777-777777777771';

    private InMemoryOrderEventReadRepository $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->events = new InMemoryOrderEventReadRepository();
        $this->events->addOrder(self::ORDER_ID, self::STORE_ID);
        $this->app->instance(OrderEventReadRepository::class, $this->events);
    }

    #[Test]
    public function an_admin_sees_the_documented_event_fields_only(): void
    {
        $this->seedEvent('10', '2026-11-20T10:00:00.123456+00:00', 'created', 'stock_reserved');

        $response = $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::ADMIN)),
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 10)
            ->assertJsonPath('data.0.from_status', 'created')
            ->assertJsonPath('data.0.to_status', 'stock_reserved')
            ->assertJsonPath('data.0.order_item_id', '01930000-0000-7000-8000-000000000001')
            ->assertJsonPath('data.0.actor_role', 'system')
            ->assertJsonPath('data.0.occurred_at', '2026-11-20T10:00:00.123456+00:00')
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.next_cursor', null);

        $event = $response->json('data.0');

        self::assertIsArray($event);
        self::assertArrayNotHasKey('actor_id', $event);
        self::assertArrayNotHasKey('payload', $event);
    }

    #[Test]
    public function an_analyst_can_read_events(): void
    {
        $this->seedEvent('10', '2026-11-20T10:00:00.123456+00:00', 'created', 'stock_reserved');

        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::ANALYST)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_customer_is_forbidden(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::CUSTOMER_ID)),
        )->assertStatus(403)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_store_manager_sees_events_for_their_own_store(): void
    {
        $this->seedEvent('10', '2026-11-20T10:00:00.123456+00:00', 'created', 'stock_reserved');

        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID)),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_store_manager_cannot_see_events_of_another_store(): void
    {
        $this->seedEvent('10', '2026-11-20T10:00:00.123456+00:00', 'created', 'stock_reserved');

        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::OTHER_STORE_ID)),
        )->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_token_is_required(): void
    {
        $this->getJson('/api/v1/orders/' . self::ORDER_ID . '/events')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function a_missing_order_returns_404(): void
    {
        $this->getJson(
            '/api/v1/orders/77777777-7777-4777-8777-777777777779/events',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404);
    }

    #[Test]
    public function an_invalid_cursor_returns_422_problem_json(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events?cursor=not-a-cursor',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('title', 'Unprocessable Entity');
    }

    #[Test]
    public function a_limit_above_the_maximum_returns_422_problem_json(): void
    {
        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events?limit=101',
            $this->headers($this->tokenFor(Role::ADMIN)),
        )->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function it_paginates_with_a_cursor(): void
    {
        $this->seedEvent('1', '2026-11-20T10:00:00.100000+00:00', 'created', 'stock_reserved');
        $this->seedEvent('2', '2026-11-20T11:00:00.100000+00:00', 'stock_reserved', 'payment_pending');

        $token = $this->tokenFor(Role::ADMIN);
        $first = $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events?limit=1',
            $this->headers($token),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1);

        $cursor = $first->json('meta.next_cursor');

        self::assertIsString($cursor);

        $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events?limit=1&cursor=' . urlencode($cursor),
            $this->headers($token),
        )->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 2)
            ->assertJsonPath('meta.next_cursor', null);
    }

    #[Test]
    public function the_default_limit_is_fifty(): void
    {
        for ($index = 1; $index <= 55; $index++) {
            $this->seedEvent(
                (string) $index,
                sprintf('2026-11-20T10:%02d:00.100000+00:00', $index),
                'created',
                'stock_reserved',
            );
        }

        $response = $this->getJson(
            '/api/v1/orders/' . self::ORDER_ID . '/events',
            $this->headers($this->tokenFor(Role::ADMIN)),
        );

        $response->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.limit', 50);

        self::assertIsString($response->json('meta.next_cursor'));
    }

    private function seedEvent(
        string $id,
        string $occurredAt,
        ?string $fromStatus,
        string $toStatus,
    ): void {
        $this->events->addEvent(new OrderEventItem(
            id: $id,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            orderItemId: '01930000-0000-7000-8000-000000000001',
            actorRole: 'system',
            occurredAt: CarbonImmutable::parse($occurredAt),
        ), self::ORDER_ID, self::STORE_ID);
    }
}
