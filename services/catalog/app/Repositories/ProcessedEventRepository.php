<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProcessedEventRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ProcessedEventRepository implements ProcessedEventRepositoryInterface
{
    public function markProcessed(string $eventId, string $routingKey, CarbonImmutable $at): bool
    {
        return DB::table('processed_events')->insertOrIgnore([
            'event_id' => $eventId,
            'routing_key' => $routingKey,
            'processed_at' => $at,
        ]) === 1;
    }
}
