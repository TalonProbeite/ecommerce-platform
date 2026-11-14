<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RequeueOutboxFailedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'outbox:failed:requeue {event_id : UUID of the quarantined event}';

    /**
     * @var string
     */
    protected $description = 'Move a quarantined outbound event from outbox_failed back to outbox';

    public function handle(): int
    {
        $argument = $this->argument('event_id');
        $eventId = is_string($argument) ? $argument : '';

        try {
            $row = DB::transaction(function () use ($eventId): ?object {
                $row = DB::table('outbox_failed')
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    return null;
                }

                DB::table('outbox')->insert([
                    'event_id' => $row->event_id,
                    'routing_key' => $row->routing_key,
                    'aggregate_key' => $row->aggregate_key,
                    'payload' => $row->payload,
                    'occurred_at' => $row->occurred_at,
                    'published_at' => null,
                    'attempts' => 0,
                    'last_error' => null,
                ]);

                DB::table('outbox_failed')->where('id', $row->id)->delete();

                return $row;
            });
        } catch (QueryException $exception) {
            $this->error(sprintf('Failed to requeue event "%s": %s', $eventId, $exception->getMessage()));

            return self::FAILURE;
        }

        if ($row === null) {
            $this->error(sprintf('Failed outbox event "%s" was not found.', $eventId));

            return self::FAILURE;
        }

        Log::info('Requeued a failed outbox event.', [
            'event_id' => $eventId,
            'routing_key' => $row->routing_key,
            'aggregate_key' => $row->aggregate_key,
        ]);

        $this->info(sprintf('Event "%s" was moved back to outbox.', $eventId));

        return self::SUCCESS;
    }
}
