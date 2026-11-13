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
    public function insert(
        string $eventId,
        string $routingKey,
        array $payload,
        CarbonImmutable $occurredAt,
        ?string $aggregateKey = null,
    ): void {
        DB::table('outbox')->insert([
            'event_id' => $eventId,
            'routing_key' => $routingKey,
            'aggregate_key' => $aggregateKey,
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
            // Preserve per-aggregate order: a pending row waits for earlier rows of the
            // same aggregate and stays blocked while that aggregate is quarantined.
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('outbox as earlier')
                    ->whereNull('earlier.published_at')
                    ->whereColumn('earlier.id', '<', 'outbox.id')
                    ->whereNotNull('outbox.aggregate_key')
                    ->whereColumn('earlier.aggregate_key', 'outbox.aggregate_key');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('outbox_failed as failed')
                    ->whereNotNull('outbox.aggregate_key')
                    ->whereColumn('failed.aggregate_key', 'outbox.aggregate_key');
            })
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
                aggregateKey: $record->aggregate_key !== null ? (string) $record->aggregate_key : null,
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

    public function recordFailure(OutboxRow $row, string $error, int $maxAttempts): bool
    {
        $attempts = $row->attempts + 1;
        $maxAttempts = max(1, $maxAttempts);

        if ($attempts < $maxAttempts) {
            DB::table('outbox')
                ->where('id', $row->id)
                ->update([
                    'attempts' => $attempts,
                    'last_error' => $error,
                ]);

            return false;
        }

        DB::table('outbox_failed')->insert([
            'event_id' => $row->eventId,
            'routing_key' => $row->routingKey,
            'aggregate_key' => $row->aggregateKey,
            'payload' => json_encode($row->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $row->occurredAt->format(self::TIMESTAMP_FORMAT),
            'failed_at' => CarbonImmutable::now()->format(self::TIMESTAMP_FORMAT),
            'attempts' => $attempts,
            'last_error' => $error,
        ]);

        DB::table('outbox')->where('id', $row->id)->delete();

        return true;
    }
}
