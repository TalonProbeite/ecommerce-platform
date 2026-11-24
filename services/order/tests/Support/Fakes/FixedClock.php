<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Clock;
use Carbon\CarbonImmutable;

final class FixedClock implements Clock
{
    public function __construct(private CarbonImmutable $now)
    {
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function setNow(CarbonImmutable $now): void
    {
        $this->now = $now;
    }
}
