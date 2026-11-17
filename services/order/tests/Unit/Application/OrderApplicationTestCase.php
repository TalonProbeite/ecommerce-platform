<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use Carbon\CarbonImmutable;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;
use Tests\Support\Fakes\InMemoryPaymentRepository;
use Tests\Support\Fakes\SequenceIdGenerator;
use Tests\TestCase;
use Tests\Unit\Domain\OrderFixtures;

abstract class OrderApplicationTestCase extends TestCase
{
    use OrderFixtures;

    protected CarbonImmutable $now;
    protected FixedClock $clock;
    protected FakeOutboxWriter $outbox;
    protected InMemoryOrderRepository $orders;
    protected InMemoryPaymentRepository $payments;
    protected FakePaymentGateway $gateway;
    protected SequenceIdGenerator $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00');
        $this->clock = new FixedClock($this->now);
        $this->outbox = new FakeOutboxWriter();
        $this->orders = new InMemoryOrderRepository($this->outbox, $this->clock);
        $this->payments = new InMemoryPaymentRepository();
        $this->gateway = new FakePaymentGateway();
        $this->ids = new SequenceIdGenerator();
    }

    protected function customer(?string $id = null): AuthUser
    {
        return new AuthUser($id ?? self::CUSTOMER_ID, Role::CUSTOMER);
    }

    protected function manager(?string $storeId = null, ?string $id = null): AuthUser
    {
        return new AuthUser(
            $id ?? '99999999-9999-4999-8999-999999999999',
            Role::STORE_MANAGER,
            $storeId ?? self::STORE_ID,
        );
    }

    protected function admin(): AuthUser
    {
        return new AuthUser('99999999-9999-4999-8999-888888888888', Role::ADMIN);
    }

    /**
     * @param list<OrderItem>|null $items
     */
    protected function seedOrder(
        OrderStatus $status,
        ?array $items = null,
        ?string $id = null,
        ?CarbonImmutable $createdAt = null,
    ): Order {
        $order = $createdAt === null
            ? $this->makeOrder($id, $items)
            : Order::create(
                id: $id ?? self::ORDER_ID,
                customerId: self::CUSTOMER_ID,
                storeId: self::STORE_ID,
                items: $items ?? [$this->makeItem()],
                idempotencyKey: 'seed-' . ($id ?? self::ORDER_ID),
                requestFingerprint: hash('sha256', 'seed-' . ($id ?? self::ORDER_ID)),
                createdAt: $createdAt,
            );

        if ($status !== OrderStatus::CREATED) {
            $order->markStockReserved($this->now);
        }

        if (in_array($status, [OrderStatus::PAYMENT_PENDING, OrderStatus::PAID, OrderStatus::IN_RENTAL, OrderStatus::COMPLETED], true)) {
            $order->markPaymentPending($this->now);
        }

        if (in_array($status, [OrderStatus::PAID, OrderStatus::IN_RENTAL, OrderStatus::COMPLETED], true)) {
            $order->markPaid($this->now);
        }

        if (in_array($status, [OrderStatus::IN_RENTAL, OrderStatus::COMPLETED], true)) {
            $order->issueItems($this->itemIds($order), $this->now, $this->manager()->id);
        }

        if ($status === OrderStatus::COMPLETED) {
            $order->returnItems($this->itemIds($order), $this->now, $this->manager()->id);
        }

        if ($status === OrderStatus::CANCELLED) {
            $order->cancel(CancelReason::CUSTOMER_REQUEST, ActorRole::CUSTOMER, $this->now);
        }

        $this->orders->save($order);
        $this->outbox->reset();
        $this->orders->resetJournal();

        return $order;
    }

    /**
     * @param list<OrderItem> $items
     */
    protected function makeOrderWithItems(array $items, ?string $id = null): Order
    {
        return $this->makeOrder($id, $items);
    }

    /**
     * @return list<string>
     */
    protected function itemIds(Order $order): array
    {
        return array_map(
            static fn (OrderItem $item): string => $item->lineId(),
            $order->items(),
        );
    }

    protected function seedPayment(
        string $orderId,
        string $status = PaymentStatus::REQUIRES_PAYMENT_METHOD,
        int $amount = 7000,
        string $providerPaymentId = 'pi_test_1',
        ?string $id = null,
    ): PaymentRecord {
        $payment = new PaymentRecord(
            id: $id ?? '01930000-0000-7000-8000-0000000000f1',
            orderId: $orderId,
            provider: 'stripe',
            providerPaymentId: $providerPaymentId,
            amount: $amount,
            currency: 'EUR',
            status: $status,
            failureCode: null,
            succeededAt: $status === PaymentStatus::SUCCEEDED ? $this->now : null,
            createdAt: $this->now,
            updatedAt: $this->now,
        );

        $this->payments->insert($payment);

        return $payment;
    }

    protected function seedRefund(
        string $orderId,
        string $reason = 'order_cancelled',
        string $status = RefundStatus::SUCCEEDED,
        int $amount = 7000,
        ?string $orderItemId = null,
        string $paymentId = '01930000-0000-7000-8000-0000000000f1',
        string $id = '01930000-0000-7000-8000-0000000000e1',
    ): RefundRecord {
        $refund = new RefundRecord(
            id: $id,
            orderId: $orderId,
            orderItemId: $orderItemId,
            paymentId: $paymentId,
            providerRefundId: 're_existing',
            amount: $amount,
            reason: $reason,
            status: $status,
            createdAt: $this->now,
            updatedAt: $this->now,
        );

        $this->payments->insertRefund($refund);

        return $refund;
    }
}
