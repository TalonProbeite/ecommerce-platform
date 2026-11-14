<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Outbox;

use App\Contracts\Outbox\OutboxMessagePublisherInterface;
use App\Domain\Contracts\EventInterface;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\CategoryUpdated;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\ProductUpdated;
use App\Messaging\Events\StockChanged;
use App\Messaging\Events\StockReservationFailed;
use App\Messaging\Events\StockReservationFailureReason;
use App\Messaging\Events\StockReserved;
use App\Messaging\RoutingKeys;
use App\Outbox\OutboxRelay;
use App\Outbox\OutboxRepository;
use App\Outbox\OutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
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
    public function relay_failure_on_second_row_allows_unrelated_orders_to_progress(): void
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

        self::assertSame(2, $count);
        self::assertSame([$id1, $id3], $fakePublisher->published);

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

        // Row 3: unrelated order progresses despite row 2 failing
        self::assertNotNull($row3->published_at);
        self::assertSame(0, $row3->attempts);
        self::assertNull($row3->last_error);
    }

    #[Test]
    public function relay_retries_until_max_attempts_then_quarantines_the_row(): void
    {
        config(['outbox.batch_size' => 10, 'outbox.max_attempts' => 3]);

        $failed = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);

        $publisher = new class () implements OutboxMessagePublisherInterface {
            public function publish(string $routingKey, array $body, string $messageId): void
            {
                throw new RuntimeException('Persistent failure');
            }
        };

        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $relay = new OutboxRelay($this->repository, $publisher, $logger);

        self::assertSame(0, $relay->processBatch());
        self::assertSame(1, DB::table('outbox')->where('event_id', $failed)->value('attempts'));
        self::assertSame(0, $relay->processBatch());
        self::assertSame(2, DB::table('outbox')->where('event_id', $failed)->value('attempts'));
        self::assertSame(0, $relay->processBatch());

        self::assertFalse(DB::table('outbox')->where('event_id', $failed)->exists());

        $quarantined = DB::table('outbox_failed')->where('event_id', $failed)->first();
        self::assertNotNull($quarantined);
        self::assertSame('stock.reserved', $quarantined->routing_key);
        self::assertSame('order-1', $quarantined->aggregate_key);
        self::assertSame(3, $quarantined->attempts);
        self::assertStringContainsString('Persistent failure', (string) $quarantined->last_error);
        self::assertSame('order-1', json_decode((string) $quarantined->payload, true, 512, JSON_THROW_ON_ERROR)['order_id']);

        $critical = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === 'critical',
        ));
        self::assertCount(1, $critical);
        self::assertSame($failed, $critical[0]['context']['event_id']);
        self::assertSame('stock.reserved', $critical[0]['context']['routing_key']);
    }

    #[Test]
    public function same_aggregate_events_wait_until_the_quarantined_row_is_resolved(): void
    {
        config(['outbox.batch_size' => 10, 'outbox.max_attempts' => 2]);

        $failed = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $sameAggregate = $this->writer->enqueue(new StockReserved('order-1', 'store-1'), $this->now);
        $otherAggregate = $this->writer->enqueue(new StockReserved('order-2', 'store-1'), $this->now);

        $publisher = new class ($failed) implements OutboxMessagePublisherInterface {
            public bool $repaired = false;
            /** @var list<string> */
            public array $published = [];

            public function __construct(private readonly string $failed)
            {
            }

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                if ($messageId === $this->failed && ! $this->repaired) {
                    throw new RuntimeException('Persistent failure');
                }

                $this->published[] = $messageId;
            }
        };

        $relay = new OutboxRelay($this->repository, $publisher, new NullLogger());

        // Row 1 fails, its same-aggregate sibling is skipped, and the unrelated order proceeds.
        self::assertSame(1, $relay->processBatch());
        self::assertSame([$otherAggregate], $publisher->published);
        self::assertNull(DB::table('outbox')->where('event_id', $sameAggregate)->value('published_at'));

        // The retry reaches the limit and moves the poison row to outbox_failed.
        self::assertSame(0, $relay->processBatch());
        self::assertTrue(DB::table('outbox_failed')->where('event_id', $failed)->exists());
        self::assertFalse(DB::table('outbox')->where('event_id', $failed)->exists());
        self::assertSame(0, $relay->processBatch());
        self::assertSame([$otherAggregate], $publisher->published);

        // Manual resolution unblocks the waiting sibling.
        $publisher->repaired = true;
        DB::table('outbox_failed')->where('event_id', $failed)->delete();
        self::assertSame(1, $relay->processBatch());
        self::assertSame([$otherAggregate, $sameAggregate], $publisher->published);
    }

    #[Test]
    public function writer_assigns_the_expected_aggregate_key_for_every_routing_key(): void
    {
        /** @var array<string, array{EventInterface, string}> $cases */
        $cases = [
            RoutingKeys::PRODUCT_CREATED => [new ProductCreated('product-1', 'store-1', 'Drill', 100, 'category-1', 0, true), 'product-1'],
            RoutingKeys::PRODUCT_UPDATED => [new ProductUpdated('product-2', 'store-1', 'Drill', 100, 'category-1', 0, true), 'product-2'],
            RoutingKeys::PRODUCT_DELETED => [new ProductDeleted('product-3', 'store-1'), 'product-3'],
            RoutingKeys::CATEGORY_CREATED => [new CategoryCreated('category-1', 'store-1', 'Tools', 'tools', null), 'category-1'],
            RoutingKeys::CATEGORY_UPDATED => [new CategoryUpdated('category-2', 'store-1', 'Tools', 'tools', null), 'category-2'],
            RoutingKeys::CATEGORY_DELETED => [new CategoryDeleted('category-3', 'store-1'), 'category-3'],
            RoutingKeys::STOCK_CHANGED => [new StockChanged('product-4', 'store-1', 10, 2), 'product-4'],
            RoutingKeys::STOCK_RESERVED => [new StockReserved('order-1', 'store-1'), 'order-1'],
            RoutingKeys::STOCK_RESERVATION_FAILED => [
                new StockReservationFailed('order-2', 'store-1', StockReservationFailureReason::InsufficientStock),
                'order-2',
            ],
        ];

        self::assertSame(RoutingKeys::all(), array_keys($cases));

        foreach ($cases as $routingKey => [$event, $expectedAggregateKey]) {
            $eventId = $this->writer->enqueue($event, $this->now);
            $row = DB::table('outbox')->where('event_id', $eventId)->first();

            self::assertNotNull($row);
            self::assertSame($routingKey, $row->routing_key);
            self::assertSame($expectedAggregateKey, $row->aggregate_key);
        }
    }

    #[Test]
    public function writer_logs_a_warning_and_stores_null_when_the_aggregate_key_is_missing(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $writer = new OutboxWriter($this->repository, $logger);
        $event = new ProductDeleted('', 'store-1');
        $eventId = $writer->enqueue($event, $this->now);

        self::assertNull(DB::table('outbox')->where('event_id', $eventId)->value('aggregate_key'));
        self::assertCount(1, $logger->messages);
    }

    #[Test]
    public function relay_message_body_contains_the_full_envelope_matching_message_id(): void
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
        self::assertSame(1, $fakePublisher->lastBody['schema_version']);
        self::assertSame('2026-11-20T10:00:00.000000+00:00', $fakePublisher->lastBody['occurred_at']);
        self::assertMatchesRegularExpression('/\.\d{6}[+-]\d{2}:\d{2}$/', (string) $fakePublisher->lastBody['occurred_at']);
        self::assertSame('order-123', $fakePublisher->lastBody['order_id']);
        self::assertSame('store-456', $fakePublisher->lastBody['store_id']);
    }

    #[Test]
    public function relay_converts_occurred_at_to_utc_before_formatting(): void
    {
        $eventId = $this->writer->enqueue(
            new StockReserved('order-123', 'store-456'),
            CarbonImmutable::parse('2026-11-20T13:00:00.123456+03:00'),
        );

        $fakePublisher = new class () implements OutboxMessagePublisherInterface {
            /** @var array<string, mixed> */
            public array $lastBody = [];

            public function publish(string $routingKey, array $body, string $messageId): void
            {
                $this->lastBody = $body;
            }
        };

        $relay = new OutboxRelay($this->repository, $fakePublisher, new NullLogger());
        $relay->processBatch();

        self::assertSame($eventId, $fakePublisher->lastBody['event_id']);
        // The outbox column stores whole seconds, so the formatter emits .000000.
        self::assertSame('2026-11-20T10:00:00.000000+00:00', $fakePublisher->lastBody['occurred_at']);
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
