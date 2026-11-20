<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Exceptions\InvalidItemTransition;
use App\Domain\Exceptions\InvalidQuantity;
use App\Domain\Money\Money;
use App\Domain\Order\ItemStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderItemTest extends TestCase
{
    use OrderFixtures;

    #[Test]
    public function it_calculates_line_and_deposit_totals(): void
    {
        $item = $this->makeItem(quantity: 3);

        self::assertSame(2, $item->billedHours());
        self::assertSame(6000, $item->lineTotal()->amount());
        self::assertSame(15000, $item->depositTotal()->amount());
        self::assertSame(ItemStatus::RESERVED, $item->status());
    }

    #[Test]
    public function quantity_must_be_within_the_contract_limits(): void
    {
        $this->expectException(InvalidQuantity::class);
        $this->makeItem(quantity: 0);
    }

    #[Test]
    public function quantity_above_one_thousand_is_rejected(): void
    {
        $this->expectException(InvalidQuantity::class);
        $this->makeItem(quantity: 1001);
    }

    #[Test]
    public function a_reserved_item_can_be_issued_only_once(): void
    {
        $item = $this->makeItem();
        $at = $this->at('2026-11-21T09:00:00.000000+00:00');

        self::assertTrue($item->issue('actor-1', $at));
        self::assertSame(ItemStatus::ISSUED, $item->status());
        self::assertSame($at, $item->issuedAt());
        self::assertSame('actor-1', $item->issuedBy());
        self::assertFalse($item->issue('actor-2', $at->addMinute()));
    }

    #[Test]
    public function an_issued_item_can_be_returned_only_once(): void
    {
        $item = $this->makeItem();
        $issuedAt = $this->at('2026-11-21T09:00:00.000000+00:00');
        $returnedAt = $issuedAt->addHour();

        $item->issue('actor-1', $issuedAt);

        self::assertTrue($item->return('actor-2', $returnedAt));
        self::assertSame(ItemStatus::RETURNED, $item->status());
        self::assertSame($returnedAt, $item->returnedAt());
        self::assertSame('actor-2', $item->returnedBy());
        self::assertFalse($item->return('actor-3', $returnedAt->addMinute()));
    }

    #[Test]
    public function a_cancelled_item_cannot_be_issued(): void
    {
        $item = $this->makeItem();
        $item->cancel();

        $this->expectException(InvalidItemTransition::class);

        $item->issue('actor-1', CarbonImmutable::parse('2026-11-21T09:00:00+00:00'));
    }

    #[Test]
    public function a_reserved_item_cannot_be_returned(): void
    {
        $this->expectException(InvalidItemTransition::class);

        $this->makeItem()->return('actor-1', CarbonImmutable::parse('2026-11-21T09:00:00+00:00'));
    }

    #[Test]
    public function it_keeps_the_unit_price_in_minor_units(): void
    {
        $item = $this->makeItem(pricePerHour: Money::fromMinorUnits(1500));

        self::assertSame(1500, $item->pricePerHour()->amount());
        self::assertSame(3000, $item->lineTotal()->amount());
    }
}
