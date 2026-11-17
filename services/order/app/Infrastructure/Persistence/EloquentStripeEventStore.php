<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\StripeEventStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentStripeEventStore implements StripeEventStore
{
    public function markProcessed(string $eventId, string $type, CarbonImmutable $at): bool
    {
        return DB::table('stripe_events')->insertOrIgnore([
            'id' => $eventId,
            'type' => $type,
            'processed_at' => $at->format('Y-m-d H:i:s.uP'),
        ]) === 1;
    }
}
