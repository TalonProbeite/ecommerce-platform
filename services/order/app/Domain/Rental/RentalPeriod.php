<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use App\Domain\Exceptions\InvalidRentalPeriod;
use Carbon\CarbonImmutable;

final readonly class RentalPeriod
{
    public function __construct(
        private CarbonImmutable $start,
        private CarbonImmutable $end,
    ) {
        if ($start->greaterThanOrEqualTo($end)) {
            throw new InvalidRentalPeriod($start, $end);
        }
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    public function billedHours(): int
    {
        $microseconds = (int) $this->start->diffInMicroseconds($this->end);
        $hourInMicroseconds = 3_600_000_000;

        return max(1, intdiv($microseconds + $hourInMicroseconds - 1, $hourInMicroseconds));
    }

    public function seconds(): int
    {
        $microseconds = (int) $this->start->diffInMicroseconds($this->end);

        return intdiv($microseconds + 999_999, 1_000_000);
    }
}
