<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money\Money;
use App\Domain\Pricing\PriceCalculator;
use App\Domain\Pricing\PriceLine;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    use OrderFixtures;

    #[Test]
    public function it_calculates_item_totals_and_order_totals(): void
    {
        $result = (new PriceCalculator())->calculate([
            new PriceLine(
                storeId: self::STORE_ID,
                productId: '33333333-3333-4333-8333-333333333333',
                productName: 'Drill',
                pricePerHour: Money::fromMinorUnits(1000),
                depositPerUnit: Money::fromMinorUnits(5000),
                quantity: 3,
                rentalPeriod: $this->rentalPeriod(),
            ),
            new PriceLine(
                storeId: self::STORE_ID,
                productId: '44444444-4444-4444-8444-444444444444',
                productName: 'Saw',
                pricePerHour: Money::fromMinorUnits(2000),
                depositPerUnit: Money::fromMinorUnits(7000),
                quantity: 1,
                rentalPeriod: $this->rentalPeriod(),
            ),
        ]);

        self::assertCount(2, $result->items);
        self::assertSame(self::STORE_ID, $result->items[0]->storeId);
        self::assertSame(2, $result->items[0]->billedHours);
        self::assertSame(6000, $result->items[0]->lineTotal->amount());
        self::assertSame(15000, $result->items[0]->depositTotal->amount());
        self::assertSame(4000, $result->items[1]->lineTotal->amount());
        self::assertSame(10000, $result->subtotal->amount());
        self::assertSame(22000, $result->deposit->amount());
        self::assertSame(32000, $result->total()->amount());
    }

    #[Test]
    public function an_empty_item_list_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PriceCalculator())->calculate([]);
    }

    #[Test]
    public function a_priced_item_can_be_converted_to_an_order_item(): void
    {
        $result = (new PriceCalculator())->calculate([
            new PriceLine(
                storeId: self::STORE_ID,
                productId: '33333333-3333-4333-8333-333333333333',
                productName: 'Drill',
                pricePerHour: Money::fromMinorUnits(1000),
                depositPerUnit: Money::fromMinorUnits(5000),
                quantity: 2,
                rentalPeriod: $this->rentalPeriod(),
            ),
        ]);

        $item = $result->items[0]->toOrderItem('01930000-0000-7000-8000-000000000001');

        self::assertSame('01930000-0000-7000-8000-000000000001', $item->lineId());
        self::assertSame(self::STORE_ID, $item->storeId());
        self::assertSame(4000, $item->lineTotal()->amount());
        self::assertSame(10000, $item->depositTotal()->amount());
    }
}
