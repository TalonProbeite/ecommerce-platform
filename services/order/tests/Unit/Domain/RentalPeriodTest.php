<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Exceptions\InvalidRentalPeriod;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RentalPeriodTest extends TestCase
{
    #[Test]
    public function start_must_be_strictly_before_end(): void
    {
        $at = CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00');

        $this->expectException(InvalidRentalPeriod::class);

        new RentalPeriod($at, $at);
    }

    #[Test]
    public function end_before_start_is_rejected(): void
    {
        $this->expectException(InvalidRentalPeriod::class);

        new RentalPeriod(
            CarbonImmutable::parse('2026-11-21T11:00:00.000000+00:00'),
            CarbonImmutable::parse('2026-11-21T09:00:00.000000+00:00'),
        );
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function billedHoursProvider(): iterable
    {
        yield 'thirty minutes is billed as one hour' => [
            '2026-11-21T09:00:00.000000+00:00',
            '2026-11-21T09:30:00.000000+00:00',
            1,
        ];
        yield 'exactly one hour is billed as one hour' => [
            '2026-11-21T09:00:00.000000+00:00',
            '2026-11-21T10:00:00.000000+00:00',
            1,
        ];
        yield 'one hour and one microsecond is billed as two hours' => [
            '2026-11-21T09:00:00.000000+00:00',
            '2026-11-21T10:00:00.000001+00:00',
            2,
        ];
        yield 'ninety minutes is billed as two hours' => [
            '2026-11-21T09:00:00.000000+00:00',
            '2026-11-21T10:30:00.000000+00:00',
            2,
        ];
        yield 'two hours is billed as two hours' => [
            '2026-11-21T09:00:00.000000+00:00',
            '2026-11-21T11:00:00.000000+00:00',
            2,
        ];
    }

    #[Test]
    #[DataProvider('billedHoursProvider')]
    public function billed_hours_round_up_with_a_one_hour_minimum(string $start, string $end, int $hours): void
    {
        $period = new RentalPeriod(
            CarbonImmutable::parse($start),
            CarbonImmutable::parse($end),
        );

        self::assertSame($hours, $period->billedHours());
    }
}
