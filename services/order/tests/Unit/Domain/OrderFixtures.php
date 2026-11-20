<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money\Money;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;

trait OrderFixtures
{
    protected const string ORDER_ID = '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e';
    protected const string CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    protected const string STORE_ID = '22222222-2222-4222-8222-222222222222';
    protected const string CREATED_AT = '2026-11-20T10:00:00.123456+00:00';

    protected function at(string $value = self::CREATED_AT): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }

    protected function rentalPeriod(): RentalPeriod
    {
        return new RentalPeriod(
            CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
            CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
        );
    }

    protected function makeItem(
        string $lineId = '01930000-0000-7000-8000-000000000001',
        int $quantity = 1,
        ?Money $pricePerHour = null,
        ?Money $depositPerUnit = null,
        ?string $productId = null,
        ?string $storeId = null,
    ): OrderItem {
        return OrderItem::create(
            lineId: $lineId,
            storeId: $storeId ?? self::STORE_ID,
            productId: $productId ?? '33333333-3333-4333-8333-333333333333',
            productName: 'Drill',
            quantity: $quantity,
            rentalPeriod: $this->rentalPeriod(),
            pricePerHour: $pricePerHour ?? Money::fromMinorUnits(1000),
            depositPerUnit: $depositPerUnit ?? Money::fromMinorUnits(5000),
        );
    }

    /**
     * @param list<OrderItem>|null $items
     */
    protected function makeOrder(
        ?string $id = null,
        ?array $items = null,
        string $idempotencyKey = '01930000-0000-7000-8000-0000000000aa',
    ): Order {
        return Order::create(
            id: $id ?? self::ORDER_ID,
            customerId: self::CUSTOMER_ID,
            storeId: self::STORE_ID,
            items: $items ?? [$this->makeItem()],
            idempotencyKey: $idempotencyKey,
            requestFingerprint: hash('sha256', $idempotencyKey),
            createdAt: $this->at(),
        );
    }
}
