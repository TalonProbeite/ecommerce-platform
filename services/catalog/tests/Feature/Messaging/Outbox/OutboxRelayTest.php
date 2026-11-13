<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
use App\Messaging\Events\StockReserved;
use App\Outbox\OutboxRelay;
use App\Outbox\OutboxRepository;
use App\Outbox\OutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\PostgresTestCase;

final class OutboxRelayTest extends PostgresTestCase
{
    private OutboxRepository $repository;
    private OutboxWriter $writer;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OutboxRepository();
        $this->writer = new OutboxWriter($this->repository);
        $this->now = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');
    }

    #[Test]
    public function enqueue_inserts_unique_event_id_and_ascending_id(): void
    {
        $id1 = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $id2 = $this->writer->enqueue(new StockReserved('order-2', 'store-1'), $this->now);
        $id3 = $this->writer->enqueue(new StockReserved('order-3', 'store-1'), $this->now);

        self::assertNotSame($id1, $id2);
        self::assertNotSame($id2, $id3);
        self::assertNotSame($id1, $id3);

        $rows = DB::table('outbox')->orderBy('id', 'asc')->get();
        self::assertCount(3, $rows);

        self::assertSame($id1, $rows[0]->event_id);
        self::assertSame($id2, $rows[1]->event_id);
        self::assertSame($id3, $rows[2]->event_id);

        self::assertLessThan($rows[1]->id, $rows[0]->id);
        self::assertLessThan($rows[2]->id, $rows[1]->id);
    }

    #[Test]
    public function lock_pending_batch_returns_rows_ordered_by_ascending_id(): void
    {
        $id1 = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $id2 = $this->writer->enqueue(new StockReserved('order-2', 'store-1'), $this->now);
        $id3 = $this->writer->enqueue(new StockReserved('order-3', 'store-1'), $this->now);

        $rows = DB::transaction(fn (): array => $this->repository->lockPendingBatch(10));

        self::assertCount(3, $rows);
        self::assertSame($id1, $rows[0]->eventId);
        self::assertSame($id2, $rows[1]->eventId);
        self::assertSame($id3, $rows[2]->eventId);

        self::assertLessThan($rows[1]->id, $rows[0]->id);
        self::assertLessThan($rows[2]->id, $rows[1]->id);
    }

    #[Test]
    public function relay_with_fake_publisher_marks_rows_as_published_preserving_order(): void
    {
        $id1 = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $id2 = $this->writer->enqueue(new StockReserved('order-2', 'store-1'), $this->now);

        $fakePublisher = new class () implements OutboxMessagePublisherInterface {
            /** @var list<array{routingKey: string, body: array<string, mixed>, messageId: string}> */
            public array $published = [];

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                $this->published[] = [
                    'routingKey' => $routingKey,
                    'body' => $body,
                    'messageId' => $messageId,
                ];
            }
        };

        $relay = new OutboxRelay($this->repository, $fakePublisher, new NullLogger());
        $count = $relay->processBatch();

        self::assertSame(2, $count);
        self::assertCount(2, $fakePublisher->published);
        self::assertSame($id1, $fakePublisher->published[0]['messageId']);
        self::assertSame($id2, $fakePublisher->published[1]['messageId']);

        $dbRows = DB::table('outbox')->orderBy('id', 'asc')->get();
        self::assertNotNull($dbRows[0]->published_at);
        self::assertNotNull($dbRows[1]->published_at);
        self::assertSame(0, $dbRows[0]->attempts);
        self::assertSame(0, $dbRows[1]->attempts);
    }

    #[Test]
    public function relay_failure_on_second_row_marks_first_leaves_second_unmarked_and_aborts(): void
    {
        $id1 = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $id2 = $this->writer->enqueue(new StockReserved('order-2', 'store-1'), $this->now);
        $id3 = $this->writer->enqueue(new StockReserved('order-3', 'store-1'), $this->now);

        $fakePublisher = new class ($id2) implements OutboxMessagePublisherInterface {
            /** @var list<string> */
            public array $published = [];

            public function __construct(private readonly string $failOnId)
            {
            }

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                if ($messageId === $this->failOnId) {
                    throw new RuntimeException('Connection lost to broker during publish');
                }

                $this->published[] = $messageId;
            }
        };

        $relay = new OutboxRelay($this->repository, $fakePublisher, new NullLogger());
        $count = $relay->processBatch();

        self::assertSame(1, $count);
        self::assertSame([$id1], $fakePublisher->published);

        $row1 = DB::table('outbox')->where('event_id', $id1)->first();
        $row2 = DB::table('outbox')->where('event_id', $id2)->first();
        $row3 = DB::table('outbox')->where('event_id', $id3)->first();

        self::assertNotNull($row1);
        self::assertNotNull($row2);
        self::assertNotNull($row3);

        // Row 1: published
        self::assertNotNull($row1->published_at);
        self::assertSame(0, $row1->attempts);
        self::assertNull($row1->last_error);

        // Row 2: failed, attempts incremented, last_error recorded, published_at is null
        self::assertNull($row2->published_at);
        self::assertSame(1, $row2->attempts);
        self::assertStringContainsString('Connection lost to broker during publish', (string) $row2->last_error);

        // Row 3: untouched because batch aborted on first failure
        self::assertNull($row3->published_at);
        self::assertSame(0, $row3->attempts);
        self::assertNull($row3->last_error);
    }

    #[Test]
    public function relay_message_body_contains_event_id_and_occurred_at_with_offset_matching_message_id(): void
    {
        $eventId = $this->writer->enqueue(new StockReserved('order-123', 'store-456'), $this->now);

        $fakePublisher = new class () implements OutboxMessagePublisherInterface {
            /** @var array<string, mixed> */
            public array $lastBody = [];
            public string $lastMessageId = '';

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                $this->lastBody = $body;
                $this->lastMessageId = $messageId;
            }
        };

        $relay = new OutboxRelay($this->repository, $fakePublisher, new NullLogger());
        $relay->processBatch();

        self::assertSame($eventId, $fakePublisher->lastMessageId);
        self::assertSame($eventId, $fakePublisher->lastBody['event_id']);
        self::assertSame($this->now->toIso8601String(), $fakePublisher->lastBody['occurred_at']);
        self::assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', (string) $fakePublisher->lastBody['occurred_at']);
        self::assertSame('order-123', $fakePublisher->lastBody['order_id']);
        self::assertSame('store-456', $fakePublisher->lastBody['store_id']);
    }

    #[Test]
    #[Group('concurrency')]
    public function two_parallel_relays_do_not_publish_the_same_row_twice(): void
    {
        if (! getenv('RUN_CONCURRENCY_TESTS')) {
            self::markTestSkipped('Concurrency suite is opt-in: set RUN_CONCURRENCY_TESTS=1.');
        }

        $id = $this->writer->enqueue(new StockReserved('order-concurrent', 'store-1'), $this->now);

        // Two transactions on the same schema: Connection 1 locks row
        DB::beginTransaction();
        $batch1 = $this->repository->lockPendingBatch(10);
        self::assertCount(1, $batch1);
        self::assertSame($id, $batch1[0]->eventId);

        // A second query inside another transaction using SKIP LOCKED cannot see the locked row
        $pdo = DB::connection()->getPdo();
        $secondPdo = new \PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                (string) config('database.connections.pgsql.host'),
                (string) config('database.connections.pgsql.port'),
                (string) config('database.connections.pgsql.database'),
            ),
            (string) config('database.connections.pgsql.username'),
            (string) config('database.connections.pgsql.password'),
        );
        $secondPdo->exec('SET search_path TO ' . $this->testSchema);
        $secondPdo->beginTransaction();
        $stmt = $secondPdo->query('SELECT * FROM outbox WHERE published_at IS NULL ORDER BY id LIMIT 10 FOR UPDATE SKIP LOCKED');
        $rowsFromSecond = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $secondPdo->rollBack();

        self::assertCount(0, $rowsFromSecond);

        DB::rollBack();
    }
}
