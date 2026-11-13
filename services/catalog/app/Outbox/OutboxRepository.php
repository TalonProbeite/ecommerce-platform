<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Contracts\Outbox\OutboxRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OutboxRepository implements OutboxRepositoryInterface
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    /**
     * @param array<string, mixed> $payload
     */
    public function insert(string $eventId, string $routingKey, array $payload, CarbonImmutable $occurredAt): void
    {
        DB::table('outbox')->insert([
            'event_id' => $eventId,
            'routing_key' => $routingKey,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt->format(self::TIMESTAMP_FORMAT),
            'published_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * @return list<OutboxRow>
     */
    public function lockPendingBatch(int $limit): array
    {
        $records = DB::table('outbox')
            ->whereNull('published_at')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->lock('FOR UPDATE SKIP LOCKED')
            ->get();

        $rows = [];

        foreach ($records as $record) {
            /** @var array<string, mixed> $payload */
            $payload = is_string($record->payload)
                ? json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR)
                : (array) $record->payload;

            $rows[] = new OutboxRow(
                id: (int) $record->id,
                eventId: (string) $record->event_id,
                routingKey: (string) $record->routing_key,
                payload: $payload,
                occurredAt: CarbonImmutable::parse((string) $record->occurred_at),
                publishedAt: $record->published_at !== null ? CarbonImmutable::parse((string) $record->published_at) : null,
                attempts: (int) $record->attempts,
                lastError: $record->last_error !== null ? (string) $record->last_error : null,
            );
        }

        return $rows;
    }

    public function markPublished(int $id, CarbonImmutable $at): void
    {
        DB::table('outbox')
            ->where('id', $id)
            ->update([
                'published_at' => $at->format(self::TIMESTAMP_FORMAT),
            ]);
    }

    public function recordFailure(int $id, string $error): void
    {
        DB::table('outbox')
            ->where('id', $id)
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => $error,
            ]);
    }
}
