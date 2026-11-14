<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
use App\Messaging\Events\StockReserved;
use App\Outbox\OutboxRelay;
use App\Outbox\OutboxRepository;
use App\Outbox\OutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\PostgresTestCase;

final class OutboxFailedCommandsTest extends PostgresTestCase
{
    private OutboxRepository $repository;
    private OutboxWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OutboxRepository();
        $this->writer = new OutboxWriter($this->repository);
    }

    #[Test]
    public function requeue_moves_the_row_back_and_the_relay_publishes_it(): void
    {
        $now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');
        $eventId = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $now);

        DB::transaction(function (): void {
            $rows = $this->repository->lockPendingBatch(10);
            self::assertCount(1, $rows);
            self::assertTrue($this->repository->recordFailure($rows[0], 'Broker unavailable', 1));
        });

        self::assertTrue(DB::table('outbox_failed')->where('event_id', $eventId)->exists());
        self::assertFalse(DB::table('outbox')->where('event_id', $eventId)->exists());

        $this->artisan('outbox:failed:requeue', ['event_id' => $eventId])
            ->assertExitCode(Command::SUCCESS);

        $requeued = DB::table('outbox')->where('event_id', $eventId)->first();
        self::assertNotNull($requeued);
        self::assertSame(0, $requeued->attempts);
        self::assertNull($requeued->last_error);
        self::assertNull($requeued->published_at);
        self::assertSame('2026-11-20 10:00:00.000000+00:00', $this->formatTimestamp((string) $requeued->occurred_at));
        self::assertFalse(DB::table('outbox_failed')->where('event_id', $eventId)->exists());

        $publisher = new class () implements OutboxMessagePublisherInterface {
            /** @var list<string> */
            public array $published = [];

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                $this->published[] = $messageId;
            }
        };

        $relay = new OutboxRelay($this->repository, $publisher, new NullLogger());
        self::assertSame(1, $relay->processBatch());
        self::assertSame([$eventId], $publisher->published);
        self::assertSame(0, DB::table('outbox_failed')->count());
        self::assertNotNull(DB::table('outbox')->where('event_id', $eventId)->value('published_at'));
    }

    #[Test]
    public function requeue_returns_a_failure_code_for_an_unknown_event_id(): void
    {
        $this->artisan('outbox:failed:requeue', ['event_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function list_command_reports_failed_rows(): void
    {
        $now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');
        $eventId = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $now);

        DB::transaction(function (): void {
            $rows = $this->repository->lockPendingBatch(10);
            $this->repository->recordFailure($rows[0], 'Broker unavailable', 1);
        });

        $this->artisan('outbox:failed:list')
            ->expectsOutputToContain($eventId)
            ->assertExitCode(Command::SUCCESS);
    }

    private function formatTimestamp(string $value): string
    {
        return CarbonImmutable::parse($value)->setTimezone('+00:00')->format('Y-m-d H:i:s.uP');
    }
}
