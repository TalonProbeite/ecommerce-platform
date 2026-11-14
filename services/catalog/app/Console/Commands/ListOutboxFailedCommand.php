<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ListOutboxFailedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'outbox:failed:list {--limit=50 : Maximum number of rows to show}';

    /**
     * @var string
     */
    protected $description = 'List outbound events quarantined in outbox_failed';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::table('outbox_failed')
            ->orderBy('id')
            ->limit($limit)
            ->get([
                'id',
                'event_id',
                'routing_key',
                'aggregate_key',
                'attempts',
                'last_error',
                'failed_at',
            ]);

        if ($rows->isEmpty()) {
            $this->info('No failed outbox messages.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'event_id', 'routing_key', 'aggregate_key', 'attempts', 'last_error', 'failed_at'],
            $rows->map(static fn (object $row): array => [
                $row->id,
                $row->event_id,
                $row->routing_key,
                $row->aggregate_key,
                $row->attempts,
                $row->last_error,
                $row->failed_at,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
