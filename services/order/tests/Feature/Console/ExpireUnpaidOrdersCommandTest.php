<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Ports\PaymentGateway;
use App\Application\Ports\PaymentRepository;
use App\Domain\Events\OrderCancelled;
use App\Domain\Money\Money;
use App\Domain\Order\CancelReason;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Http\ApiTestCase;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\InMemoryPaymentRepository;

final class ExpireUnpaidOrdersCommandTest extends ApiTestCase
{
    private const string EXPIRED_ORDER_ID = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6e01';
    private const string FRESH_ORDER_ID = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6e02';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PaymentRepository::class, new InMemoryPaymentRepository());
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway());
    }

    #[Test]
    public function it_cancels_expired_unpaid_orders_and_keeps_fresh_ones(): void
    {
        $now = $this->clock->now();
        $expired = $this->newOrder(self::EXPIRED_ORDER_ID, $now->subMinutes(31));
        $expired->markStockReserved($now);
        $this->orders->save($expired);

        $fresh = $this->newOrder(self::FRESH_ORDER_ID, $now->subMinutes(29));
        $fresh->markStockReserved($now);
        $fresh->markPaymentPending($now);
        $this->orders->save($fresh);

        $this->outbox->reset();

        $this->artisan('orders:expire-unpaid')
            ->expectsOutput('Cancelled unpaid orders: 1')
            ->assertSuccessful();

        $expiredAfter = $this->orders->findById(self::EXPIRED_ORDER_ID);
        $freshAfter = $this->orders->findById(self::FRESH_ORDER_ID);

        self::assertNotNull($expiredAfter);
        self::assertNotNull($freshAfter);
        self::assertSame(OrderStatus::CANCELLED, $expiredAfter->status());
        self::assertSame(CancelReason::PAYMENT_TIMEOUT, $expiredAfter->cancelReason());
        self::assertSame(OrderStatus::PAYMENT_PENDING, $freshAfter->status());

        self::assertCount(1, $this->outbox->eventsOf(OrderCancelled::class));
    }

    private function newOrder(string $id, CarbonImmutable $createdAt): Order
    {
        return Order::create(
            id: $id,
            customerId: self::CUSTOMER_ID,
            storeId: self::STORE_ID,
            items: [OrderItem::create(
                lineId: sprintf('01930000-0000-7000-8000-%012d', abs(crc32($id))),
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
            )],
            idempotencyKey: 'expire-' . $id,
            requestFingerprint: hash('sha256', 'expire-' . $id),
            createdAt: $createdAt,
        );
    }
}
