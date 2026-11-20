<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Domain\Events\OrderCreated;
use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;

final class CreateOrderHttpTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createProduct();
    }

    #[Test]
    public function it_creates_an_order_and_returns_location(): void
    {
        $response = $this->postJson(
            '/api/v1/orders',
            $this->payload(),
            $this->orderHeaders($this->tokenFor(Role::CUSTOMER), 'key-1'),
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.customer_id', self::CUSTOMER_ID)
            ->assertJsonPath('data.store_id', self::STORE_ID)
            ->assertJsonPath('data.subtotal_amount', 4000)
            ->assertJsonPath('data.deposit_amount', 10000)
            ->assertJsonPath('data.total_amount', 14000)
            ->assertJsonPath('data.items.0.product_id', self::PRODUCT_ID)
            ->assertJsonPath('data.items.0.billed_hours', 2)
            ->assertJsonMissingPath('data.idempotency_key');

        $orderId = (string) $response->json('data.id');

        $response->assertHeader('Location', url('/api/v1/orders/' . $orderId));

        self::assertCount(1, $this->outbox->eventsOf(OrderCreated::class));
        self::assertTrue($this->locks->wasReleased('order:idem:' . self::CUSTOMER_ID . ':key-1'));
    }

    #[Test]
    public function it_returns_200_for_a_repeated_request(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER);
        $headers = $this->orderHeaders($token, 'key-repeat');

        $first = $this->postJson('/api/v1/orders', $this->payload(), $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/orders', $this->payload(), $headers);

        $second->assertStatus(200)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertHeaderMissing('Location');

        self::assertCount(1, $this->outbox->eventsOf(OrderCreated::class));
        self::assertCount(2, $this->locks->released);
    }

    #[Test]
    public function it_returns_422_for_the_same_key_with_a_different_body(): void
    {
        $token = $this->tokenFor(Role::CUSTOMER);
        $headers = $this->orderHeaders($token, 'key-different');

        $this->postJson('/api/v1/orders', $this->payload(), $headers)
            ->assertStatus(201);

        $this->postJson('/api/v1/orders', $this->payload(quantity: 1), $headers)
            ->assertStatus(422);
    }

    #[Test]
    public function it_returns_409_for_a_parallel_request(): void
    {
        $this->locks->denyAcquire = true;

        $this->postJson(
            '/api/v1/orders',
            $this->payload(),
            $this->orderHeaders($this->tokenFor(Role::CUSTOMER), 'key-parallel'),
        )->assertStatus(409);
    }

    #[Test]
    public function it_returns_422_for_a_mixed_store_order(): void
    {
        $this->createProduct(self::OTHER_PRODUCT_ID, self::OTHER_STORE_ID);

        $this->postJson(
            '/api/v1/orders',
            [
                'items' => [
                    $this->item(),
                    $this->item(self::OTHER_PRODUCT_ID),
                ],
            ],
            $this->orderHeaders($this->tokenFor(Role::CUSTOMER), 'key-mixed'),
        )->assertStatus(422);
    }

    #[Test]
    public function it_returns_422_with_product_id_for_a_deleted_product(): void
    {
        $this->createProduct(self::OTHER_PRODUCT_ID, isActive: false);

        $this->postJson(
            '/api/v1/orders',
            $this->payload(productId: self::OTHER_PRODUCT_ID),
            $this->orderHeaders($this->tokenFor(Role::CUSTOMER), 'key-deleted'),
        )->assertStatus(422)
            ->assertJsonPath('product_id', self::OTHER_PRODUCT_ID);
    }

    #[Test]
    public function it_requires_an_idempotency_key(): void
    {
        $this->postJson(
            '/api/v1/orders',
            $this->payload(),
            $this->headers($this->tokenFor(Role::CUSTOMER)),
        )->assertStatus(400);
    }

    #[Test]
    public function it_rejects_an_idempotency_key_longer_than_64_characters(): void
    {
        $this->postJson(
            '/api/v1/orders',
            $this->payload(),
            $this->orderHeaders($this->tokenFor(Role::CUSTOMER), str_repeat('a', 65)),
        )->assertStatus(400);
    }

    #[Test]
    public function it_forbids_non_customers_from_creating_orders(): void
    {
        $this->postJson(
            '/api/v1/orders',
            $this->payload(),
            $this->orderHeaders($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID), 'key-manager'),
        )->assertStatus(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $productId = self::PRODUCT_ID,
        int $quantity = 2,
    ): array {
        return ['items' => [$this->item($productId, $quantity)]];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $productId = self::PRODUCT_ID,
        int $quantity = 2,
    ): array {
        return [
            'product_id' => $productId,
            'quantity' => $quantity,
            'rental_start_at' => '2026-11-21T09:00:00.000000+00:00',
            'rental_end_at' => '2026-11-21T11:00:00.000000+00:00',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function orderHeaders(string $token, string $idempotencyKey): array
    {
        return array_merge($this->headers($token), [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }
}
