<?php

declare(strict_types=1);

namespace App\Application\Ports;

use Carbon\CarbonImmutable;

interface StripeEventStore
{
    public function markProcessed(string $eventId, string $type, CarbonImmutable $at): bool;
}
