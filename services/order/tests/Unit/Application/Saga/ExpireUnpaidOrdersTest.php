<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Saga;

use App\Application\Customer\CancelOrder\CancelOrder;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Payments\PaymentStatus;
use App\Application\Saga\ExpireUnpaidOrders\ExpireUnpaidOrders;
use App\Domain\Events\OrderCancelled;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeTransactionRunner;
use Tests\Support\Fakes\RecordingLogger;
use Tests\Unit\Application\OrderApplicationTestCase;

final class ExpireUnpaidOrdersTest extends OrderApplicationTestCase
{
    private const string OLD_STOCK_ORDER = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d01';
    private const string OLD_PENDING_ORDER = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d02';
    private const string FRESH_ORDER = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d03';

    private RecordingLogger $logger;
    private ExpireUnpaidOrders $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new RecordingLogger();

        $cancelOrder = new CancelOrder(
            new FakeTransactionRunner(),
            $this->orders,
            $this->payments,
            $this->gateway,
            $this->clock,
            $this->ids,
        );

        $this->useCase = new ExpireUnpaidOrders(
            new FakeTransactionRunner(),
            $this->orders,
            $cancelOrder,
            $this->clock,
            $this->logger,
        );
    }

    #[Test]
    public function it_cancels_old_unpaid_orders_and_leaves_fresh_orders_untouched(): void
    {
        $this->seedOrder(
            OrderStatus::STOCK_RESERVED,
            id: self::OLD_STOCK_ORDER,
            createdAt: $this->now->subMinutes(45),
        );
        $this->seedOrder(
            OrderStatus::PAYMENT_PENDING,
            id: self::OLD_PENDING_ORDER,
            createdAt: $this->now->subMinutes(31),
        );
        $this->seedOrder(
            OrderStatus::PAYMENT_PENDING,
            id: self::FRESH_ORDER,
            createdAt: $this->now->subMinutes(29),
        );

        $cancelled = $this->useCase->execute();

        self::assertSame(2, $cancelled);

        $oldStock = $this->orders->findById(self::OLD_STOCK_ORDER);
        $oldPending = $this->orders->findById(self::OLD_PENDING_ORDER);
        $fresh = $this->orders->findById(self::FRESH_ORDER);

        self::assertNotNull($oldStock);
        self::assertNotNull($oldPending);
        self::assertNotNull($fresh);
        self::assertSame(OrderStatus::CANCELLED, $oldStock->status());
        self::assertSame(CancelReason::PAYMENT_TIMEOUT, $oldStock->cancelReason());
        self::assertSame(OrderStatus::CANCELLED, $oldPending->status());
        self::assertSame(CancelReason::PAYMENT_TIMEOUT, $oldPending->cancelReason());
        self::assertSame(OrderStatus::PAYMENT_PENDING, $fresh->status());

        $events = $this->outbox->eventsOf(OrderCancelled::class);

        self::assertCount(2, $events);
        self::assertSame(ActorRole::SYSTEM, $events[0]->actorRole());
        self::assertTrue($this->logger->hasLevel('info'));
    }

    #[Test]
    public function it_continues_the_batch_when_one_order_fails(): void
    {
        $this->seedOrder(
            OrderStatus::STOCK_RESERVED,
            id: self::OLD_STOCK_ORDER,
            createdAt: $this->now->subMinutes(45),
        );
        $this->seedOrder(
            OrderStatus::PAYMENT_PENDING,
            id: self::OLD_PENDING_ORDER,
            createdAt: $this->now->subMinutes(35),
        );
        $this->seedPayment(
            self::OLD_PENDING_ORDER,
            PaymentStatus::REQUIRES_ACTION,
            providerPaymentId: 'pi_failing',
        );
        $this->gateway->cancelFailure = new PaymentProviderUnavailable('network error');

        $cancelled = $this->useCase->execute();

        self::assertSame(1, $cancelled);

        $stock = $this->orders->findById(self::OLD_STOCK_ORDER);
        $pending = $this->orders->findById(self::OLD_PENDING_ORDER);

        self::assertNotNull($stock);
        self::assertNotNull($pending);
        self::assertSame(OrderStatus::CANCELLED, $stock->status());
        self::assertSame(OrderStatus::PAYMENT_PENDING, $pending->status());
        self::assertTrue($this->logger->hasLevel('error'));
        self::assertTrue($this->logger->hasLevel('info'));
    }
}
