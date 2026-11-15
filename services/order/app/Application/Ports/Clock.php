<?php

declare(strict_types=1);

namespace App\Application\Ports;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
