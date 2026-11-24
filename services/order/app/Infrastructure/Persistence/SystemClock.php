<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Clock;
use Carbon\CarbonImmutable;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
