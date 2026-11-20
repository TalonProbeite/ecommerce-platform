<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\InMemoryPaymentRepository;

final class OrderActionHttpTest extends ApiTestCase
{
    private const string ITEM_ID = '01930000-0000-7000-8000-000000000301';
    private const string ITEM_TWO_ID = '01930000-0000-7000-8000-000000000302';
    private const string MANAGER_ID = '99999999-9999-4999-8999-999999999999';

    private InMemoryPaymentRepository $payments;
    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = new InMemoryPaymentRepository();
        $this->gateway = new FakePaymentGateway();

        $this->app->instance(PaymentRepository::class, $this->payments);
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    #[Test]
    public function it_cancels_a_paid_order_and_refunds_the_payment(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);
        $this->seedPayment($order->id(), PaymentStatus::SUCCEEDED);
        $headers = $this->headers($this->tokenFor(Role::CUSTOMER));

        $this->postJson('/api/v1/orders/' . $order->id() . '/cancel', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'customer_request');

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(7000, $this->gateway->refundCalls[0]['amount']);
        self::assertCount(1, $this->payments->refunds());

        $this->postJson('/api/v1/orders/' . $order->id() . '/cancel', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertCount(1, $this->payments->refunds());
    }

    #[Test]
    public function it_cancels_every_item_of_a_paid_order_and_refunds_the_full_amount(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID, [self::ITEM_ID, self::ITEM_TWO_ID]);
        $this->seedPayment($order->id(), PaymentStatus::SUCCEEDED, 14000);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/cancel',
            [],
            $this->headers($this->tokenFor(Role::CUSTOMER)),
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.items.0.status', 'cancelled')
            ->assertJsonPath('data.items.1.status', 'cancelled');

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(14000, $this->gateway->refundCalls[0]['amount']);
        self::assertSame(
            'order:' . $order->id() . ':refund:all',
            $this->gateway->refundCalls[0]['idempotencyKey'],
        );

        $refunds = $this->payments->refunds();

        self::assertCount(1, $refunds);
        self::assertSame('order_cancelled', $refunds[0]->reason);
        self::assertSame(14000, $refunds[0]->amount);
    }

    #[Test]
    public function it_returns_404_when_a_customer_cancels_a_foreign_order(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/cancel',
            [],
            $this->headers($this->tokenFor(Role::CUSTOMER, userId: self::OTHER_CUSTOMER_ID)),
        )->assertStatus(404);

        self::assertCount(0, $this->gateway->refundCalls);
    }

    #[Test]
    public function it_returns_409_when_cancelling_an_in_rental_order(): void
    {
        $order = $this->seedActionOrder(OrderStatus::IN_RENTAL);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/cancel',
            [],
            $this->headers($this->tokenFor(Role::CUSTOMER)),
        )->assertStatus(409);
    }

    #[Test]
    public function it_issues_and_returns_an_item_and_refunds_the_deposit(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);
        $this->seedPayment($order->id(), PaymentStatus::SUCCEEDED);
        $headers = $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID));

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/issue',
            ['item_ids' => [self::ITEM_ID]],
            $headers,
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'in_rental')
            ->assertJsonPath('skipped_item_ids', []);

        $this->clock->setNow(CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'));

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/return',
            ['item_ids' => [self::ITEM_ID]],
            $headers,
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('skipped_item_ids', []);

        self::assertCount(1, $this->gateway->refundCalls);
        self::assertSame(5000, $this->gateway->refundCalls[0]['amount']);
        self::assertSame(
            'order:' . $order->id() . ':refund:item:' . self::ITEM_ID,
            $this->gateway->refundCalls[0]['idempotencyKey'],
        );
    }

    #[Test]
    public function it_returns_skipped_item_ids_when_an_item_was_already_issued(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);
        $headers = $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID));

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/issue',
            ['item_ids' => [self::ITEM_ID]],
            $headers,
        )->assertStatus(200);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/issue',
            ['item_ids' => [self::ITEM_ID]],
            $headers,
        )->assertStatus(200)
            ->assertJsonPath('skipped_item_ids', [self::ITEM_ID]);
    }

    #[Test]
    public function it_validates_item_ids(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/issue',
            ['item_ids' => []],
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::STORE_ID)),
        )->assertStatus(422);
    }

    #[Test]
    public function it_returns_404_for_a_manager_of_another_store(): void
    {
        $order = $this->seedActionOrder(OrderStatus::PAID);

        $this->postJson(
            '/api/v1/orders/' . $order->id() . '/issue',
            ['item_ids' => [self::ITEM_ID]],
            $this->headers($this->tokenFor(Role::STORE_MANAGER, self::OTHER_STORE_ID)),
        )->assertStatus(404);
    }

    /**
     * @param list<string> $itemIds
     */
    private function seedActionOrder(OrderStatus $status, array $itemIds = [self::ITEM_ID]): Order
    {
        $now = $this->clock->now();
        $items = array_map(
            fn (string $itemId): OrderItem => $this->orderItem($itemId),
            $itemIds,
        );

        $order = Order::create(
            id: '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d31',
            customerId: self::CUSTOMER_ID,
            storeId: self::STORE_ID,
            items: $items,
            idempotencyKey: 'action-order',
            requestFingerprint: hash('sha256', 'action-order'),
            createdAt: $now,
        );

        if ($status !== OrderStatus::CREATED) {
            $order->markStockReserved($now);
        }

        if (in_array($status, [OrderStatus::PAYMENT_PENDING, OrderStatus::PAID, OrderStatus::IN_RENTAL], true)) {
            $order->markPaymentPending($now);
        }

        if (in_array($status, [OrderStatus::PAID, OrderStatus::IN_RENTAL], true)) {
            $order->markPaid($now);
        }

        if ($status === OrderStatus::IN_RENTAL) {
            $order->issueItems($itemIds, $now, self::MANAGER_ID);
        }

        $this->orders->save($order);
        $this->outbox->reset();
        $this->orders->resetJournal();

        return $order;
    }

    private function orderItem(string $lineId): OrderItem
    {
        return OrderItem::create(
            lineId: $lineId,
            storeId: self::STORE_ID,
            productId: self::PRODUCT_ID,
            productName: 'Drill',
            quantity: 1,
            rentalPeriod: new RentalPeriod(
                CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
                CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
            ),
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
        );
    }

    private function seedPayment(string $orderId, string $status, int $amount = 7000): PaymentRecord
    {
        $payment = new PaymentRecord(
            id: '01930000-0000-7000-8000-0000000000f1',
            orderId: $orderId,
            provider: 'stripe',
            providerPaymentId: 'pi_test_1',
            amount: $amount,
            currency: 'EUR',
            status: $status,
            failureCode: null,
            succeededAt: $status === PaymentStatus::SUCCEEDED ? $this->clock->now() : null,
            createdAt: $this->clock->now(),
            updatedAt: $this->clock->now(),
        );

        $this->payments->insert($payment);

        return $payment;
    }
}
