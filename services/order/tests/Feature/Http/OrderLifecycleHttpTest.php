<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Payments\StripeEvent;
use App\Application\Payments\Webhooks\HandleStripeEvent;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Application\Ports\StripeEventStore;
use App\Domain\Events\OrderIssued;
use App\Domain\Events\OrderPaid;
use App\Domain\Events\OrderReturned;
use App\Domain\Order\ItemStatus;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\Support\Fakes\InMemoryStripeEventStore;

final class OrderLifecycleHttpTest extends ApiTestCase
{
    private InMemoryPaymentRepository $payments;
    private FakePaymentGateway $gateway;
    private InMemoryStripeEventStore $stripeEvents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProduct();

        $this->payments = new InMemoryPaymentRepository();
        $this->gateway = new FakePaymentGateway();
        $this->stripeEvents = new InMemoryStripeEventStore();

        $this->app->instance(PaymentRepository::class, $this->payments);
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->app->instance(StripeEventStore::class, $this->stripeEvents);
    }

    #[Test]
    public function it_runs_the_full_create_pay_issue_and_return_path(): void
    {
        $customerHeaders = $this->headers($this->tokenFor(Role::CUSTOMER));

        $create = $this->postJson(
            '/api/v1/orders',
            ['items' => [[
                'product_id' => self::PRODUCT_ID,
                'quantity' => 2,
                'rental_start_at' => '2026-11-21T09:00:00.000000+00:00',
                'rental_end_at' => '2026-11-21T11:00:00.000000+00:00',
            ]]],
            array_merge($customerHeaders, ['Idempotency-Key' => 'lifecycle-1']),
        );

        $create->assertStatus(201)
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.total_amount', 14000);

        $orderId = (string) $create->json('data.id');
        $itemId = (string) $create->json('data.items.0.id');

        $order = $this->orders->findById($orderId);
        self::assertNotNull($order);
        $order->markStockReserved($this->clock->now());
        $this->orders->save($order);

        $this->postJson('/api/v1/orders/' . $orderId . '/payment-intent', [], $customerHeaders)
            ->assertStatus(201)
            ->assertJsonPath('amount_total', 14000);

        $this->app->make(HandleStripeEvent::class)->handle(
            new StripeEvent(
                eventId: 'evt_lifecycle_succeeded',
                type: HandleStripeEvent::PAYMENT_INTENT_SUCCEEDED,
                data: [
                    'id' => 'pi_test_1',
                    'amount_received' => 14000,
                    'currency' => 'eur',
                ],
            ),
            $this->clock->now(),
        );

        $paid = $this->orders->findById($orderId);
        self::assertNotNull($paid);
        self::assertSame(OrderStatus::PAID, $paid->status());
        self::assertCount(1, $this->outbox->eventsOf(OrderPaid::class));

        $managerHeaders = $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID));

        $this->postJson(
            '/api/v1/orders/' . $orderId . '/issue',
            ['item_ids' => [$itemId]],
            $managerHeaders,
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'in_rental')
            ->assertJsonPath('skipped_item_ids', []);

        $this->clock->setNow(CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'));

        $this->postJson(
            '/api/v1/orders/' . $orderId . '/return',
            ['item_ids' => [$itemId]],
            $managerHeaders,
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.items.0.status', 'returned')
            ->assertJsonPath('skipped_item_ids', []);

        $completed = $this->orders->findById($orderId);
        self::assertNotNull($completed);
        self::assertSame(OrderStatus::COMPLETED, $completed->status());
        self::assertNotNull($completed->completedAt());
        self::assertSame(ItemStatus::RETURNED, $completed->items()[0]->status());

        self::assertCount(1, $this->outbox->eventsOf(OrderIssued::class));
        self::assertCount(1, $this->outbox->eventsOf(OrderReturned::class));

        $refunds = $this->payments->refunds();

        self::assertCount(1, $refunds);
        self::assertSame('item_returned', $refunds[0]->reason);
        self::assertSame($itemId, $refunds[0]->orderItemId);
        self::assertSame(10000, $refunds[0]->amount);
        self::assertSame('succeeded', $refunds[0]->status);
    }
}
