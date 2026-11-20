<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Query;

use App\Application\Exceptions\InvalidCursor;
use App\Application\Query\ListOrders\ListOrdersQuery;
use App\Application\Query\ListOrders\ListOrdersUseCase;
use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemoryOrderReadRepository;

final class ListOrdersUseCaseTest extends TestCase
{
    private const string CUSTOMER_A = '11111111-1111-4111-8111-aaaaaaaaaaaa';

    private const string CUSTOMER_B = '11111111-1111-4111-8111-bbbbbbbbbbbb';

    private const string STORE_ID = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function it_returns_the_injected_orders_without_a_cursor(): void
    {
        $repository = new InMemoryOrderReadRepository([
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000001', self::CUSTOMER_A, '2026-11-20T10:00:00.000000+00:00'),
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000002', self::CUSTOMER_A, '2026-11-20T11:00:00.000000+00:00'),
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000003', self::CUSTOMER_B, '2026-11-20T12:00:00.000000+00:00'),
        ]);

        $page = (new ListOrdersUseCase($repository))->execute(
            new ListOrdersQuery(null, null, null, null, 20),
        );

        self::assertCount(3, $page->orders);
        self::assertNull($page->nextCursor);
        self::assertSame('018f3c52-6b0e-7f5a-9c1d-000000000003', $page->orders[0]->id());
    }

    #[Test]
    public function it_filters_by_status_and_customer(): void
    {
        $repository = new InMemoryOrderReadRepository([
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000001', self::CUSTOMER_A, '2026-11-20T10:00:00.000000+00:00'),
            $this->order(
                '018f3c52-6b0e-7f5a-9c1d-000000000002',
                self::CUSTOMER_A,
                '2026-11-20T11:00:00.000000+00:00',
                OrderStatus::PAID,
            ),
            $this->order(
                '018f3c52-6b0e-7f5a-9c1d-000000000003',
                self::CUSTOMER_B,
                '2026-11-20T12:00:00.000000+00:00',
                OrderStatus::PAID,
            ),
        ]);

        $useCase = new ListOrdersUseCase($repository);

        $paid = $useCase->execute(new ListOrdersQuery(null, null, OrderStatus::PAID, null, 20));
        self::assertCount(2, $paid->orders);

        $customerPaid = $useCase->execute(new ListOrdersQuery(self::CUSTOMER_A, null, OrderStatus::PAID, null, 20));
        self::assertCount(1, $customerPaid->orders);
        self::assertSame('018f3c52-6b0e-7f5a-9c1d-000000000002', $customerPaid->orders[0]->id());
    }

    #[Test]
    public function it_returns_a_cursor_when_more_orders_exist(): void
    {
        $repository = new InMemoryOrderReadRepository([
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000001', self::CUSTOMER_A, '2026-11-20T10:00:00.000000+00:00'),
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000002', self::CUSTOMER_A, '2026-11-20T11:00:00.000000+00:00'),
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000003', self::CUSTOMER_A, '2026-11-20T12:00:00.000000+00:00'),
        ]);

        $useCase = new ListOrdersUseCase($repository);

        $first = $useCase->execute(new ListOrdersQuery(null, null, null, null, 2));

        self::assertCount(2, $first->orders);
        self::assertNotNull($first->nextCursor);
        self::assertSame('018f3c52-6b0e-7f5a-9c1d-000000000002', $first->orders[1]->id());

        $second = $useCase->execute(new ListOrdersQuery(null, null, null, $first->nextCursor, 2));

        self::assertCount(1, $second->orders);
        self::assertNull($second->nextCursor);
        self::assertSame('018f3c52-6b0e-7f5a-9c1d-000000000001', $second->orders[0]->id());
    }

    #[Test]
    public function it_treats_an_empty_cursor_as_absent(): void
    {
        $repository = new InMemoryOrderReadRepository([
            $this->order('018f3c52-6b0e-7f5a-9c1d-000000000001', self::CUSTOMER_A, '2026-11-20T10:00:00.000000+00:00'),
        ]);

        $page = (new ListOrdersUseCase($repository))->execute(
            new ListOrdersQuery(null, null, null, '', 20),
        );

        self::assertCount(1, $page->orders);
    }

    #[Test]
    public function it_rejects_an_invalid_cursor(): void
    {
        $this->expectException(InvalidCursor::class);

        (new ListOrdersUseCase(new InMemoryOrderReadRepository()))->execute(
            new ListOrdersQuery(null, null, null, 'not-a-cursor', 20),
        );
    }

    private function order(
        string $id,
        string $customerId,
        string $createdAt,
        OrderStatus $status = OrderStatus::CREATED,
    ): Order {
        $timestamp = CarbonImmutable::parse($createdAt);
        $item = OrderItem::create(
            lineId: '01930000-0000-7000-8000-' . substr($id, -12),
            storeId: self::STORE_ID,
            productId: '33333333-3333-4333-8333-333333333333',
            productName: 'Drill',
            quantity: 1,
            rentalPeriod: new RentalPeriod(
                CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
                CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
            ),
            pricePerHour: Money::fromMinorUnits(1000),
            depositPerUnit: Money::fromMinorUnits(5000),
        );

        return Order::restore(
            id: $id,
            customerId: $customerId,
            storeId: self::STORE_ID,
            status: $status,
            cancelReason: null,
            items: [$item],
            subtotal: $item->lineTotal(),
            deposit: $item->depositTotal(),
            version: 1,
            idempotencyKey: 'seed-' . $id,
            requestFingerprint: hash('sha256', $id),
            createdAt: $timestamp,
            updatedAt: $timestamp,
        );
    }
}
