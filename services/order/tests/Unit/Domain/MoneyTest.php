<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function it_exposes_minor_units_and_the_single_supported_currency(): void
    {
        $money = Money::fromMinorUnits(1234);

        self::assertSame(1234, $money->amount());
        self::assertSame('EUR', $money->currency());
    }

    #[Test]
    public function it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(-1);
    }

    #[Test]
    public function it_adds_two_amounts(): void
    {
        $sum = Money::fromMinorUnits(1000)->add(Money::fromMinorUnits(250));

        self::assertSame(1250, $sum->amount());
        self::assertTrue($sum->equals(Money::fromMinorUnits(1250)));
    }

    #[Test]
    public function it_multiplies_by_a_non_negative_integer(): void
    {
        self::assertSame(3000, Money::fromMinorUnits(1000)->multiply(3)->amount());
        self::assertTrue(Money::fromMinorUnits(1000)->multiply(0)->isZero());
    }

    #[Test]
    public function it_rejects_a_negative_multiplier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(1000)->multiply(-1);
    }

    #[Test]
    public function zero_is_an_immutable_neutral_value(): void
    {
        $zero = Money::zero();

        self::assertSame(0, $zero->amount());
        self::assertSame(1000, $zero->add(Money::fromMinorUnits(1000))->amount());
    }
}
