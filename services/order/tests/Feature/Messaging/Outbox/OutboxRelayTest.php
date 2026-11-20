<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Outbox;

use App\Domain\Contracts\EventInterface;
use App\Infrastructure\Messaging\Outbox\OutboxMessagePublisherInterface;
use App\Infrastructure\Messaging\Outbox\OutboxRelay;
use App\Infrastructure\Messaging\Outbox\OutboxRepository;
use App\Infrastructure\Messaging\Outbox\OutboxWriter;
use App\Infrastructure\Messaging\Routing\OutboundRoutingKeys;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        $id1 = $this->writer->enqueue($this->event('order-1'), $this->now);
        $id2 = $this->writer->enqueue($this->event('order-2'), $this->now);
        $id3 = $this->writer->enqueue($this->event('order-3'), $this->now);

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
        $id1 = $this->writer->enqueue($this->event('order-1'), $this->now);
        $id2 = $this->writer->enqueue($this->event('order-2'), $this->now);
        $id3 = $this->writer->enqueue($this->event('order-3'), $this->now);

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
        $id1 = $this->writer->enqueue($this->event('order-1'), $this->now);
        $id2 = $this->writer->enqueue($this->event('order-2'), $this->now);

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
        $id1 = $this->writer->enqueue($this->event('order-1'), $this->now);
        $id2 = $this->writer->enqueue($this->event('order-2'), $this->now);
        $id3 = $this->writer->enqueue($this->event('order-3'), $this->now);

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

        self::assertNotNull($row1->published_at);
        self::assertSame(0, $row1->attempts);
        self::assertNull($row1->last_error);

        self::assertNull($row2->published_at);
        self::assertSame(1, $row2->attempts);
        self::assertStringContainsString('Connection lost to broker during publish', (string) $row2->last_error);

        self::assertNotNull($row3->published_at);
        self::assertSame(0, $row3->attempts);
        self::assertNull($row3->last_error);
    }

    #[Test]
    public function relay_retries_until_max_attempts_then_quarantines_the_row(): void
    {
        config(['outbox.batch_size' => 10, 'outbox.max_attempts' => 3]);

        $failed = $this->writer->enqueue($this->event('order-1'), $this->now);

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
        self::assertSame(OutboundRoutingKeys::ORDER_CREATED, $quarantined->routing_key);
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
        self::assertSame(OutboundRoutingKeys::ORDER_CREATED, $critical[0]['context']['routing_key']);
    }

    #[Test]
    public function same_aggregate_events_wait_until_the_quarantined_row_is_resolved(): void
    {
        config(['outbox.batch_size' => 10, 'outbox.max_attempts' => 2]);

        $failed = $this->writer->enqueue($this->event('order-1'), $this->now);
        $sameAggregate = $this->writer->enqueue($this->event('order-1'), $this->now);
        $otherAggregate = $this->writer->enqueue($this->event('order-2'), $this->now);

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

        self::assertSame(1, $relay->processBatch());
        self::assertSame([$otherAggregate], $publisher->published);
        self::assertNull(DB::table('outbox')->where('event_id', $sameAggregate)->value('published_at'));

        self::assertSame(0, $relay->processBatch());
        self::assertTrue(DB::table('outbox_failed')->where('event_id', $failed)->exists());
        self::assertFalse(DB::table('outbox')->where('event_id', $failed)->exists());
        self::assertSame(0, $relay->processBatch());
        self::assertSame([$otherAggregate], $publisher->published);

        $publisher->repaired = true;
        DB::table('outbox_failed')->where('event_id', $failed)->delete();
        self::assertSame(1, $relay->processBatch());
        self::assertSame([$otherAggregate, $sameAggregate], $publisher->published);
    }

    #[Test]
    public function writer_assigns_the_expected_aggregate_key_for_every_outbound_routing_key(): void
    {
        foreach (OutboundRoutingKeys::all() as $routingKey) {
            $eventId = $this->writer->enqueue($this->event('order-1', $routingKey), $this->now);
            $row = DB::table('outbox')->where('event_id', $eventId)->first();

            self::assertNotNull($row);
            self::assertSame($routingKey, $row->routing_key);
            self::assertSame('order-1', $row->aggregate_key);
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
        $eventId = $writer->enqueue($this->event(''), $this->now);

        self::assertNull(DB::table('outbox')->where('event_id', $eventId)->value('aggregate_key'));
        self::assertCount(1, $logger->messages);
    }

    #[Test]
    public function relay_message_body_contains_the_full_envelope_matching_message_id(): void
    {
        $eventId = $this->writer->enqueue($this->event('order-123', OutboundRoutingKeys::ORDER_CREATED, 'store-456'), $this->now);

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
        $this->writer->enqueue(
            $this->event('order-123', OutboundRoutingKeys::ORDER_CREATED, 'store-456'),
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

        self::assertSame('2026-11-20T10:00:00.123456+00:00', $fakePublisher->lastBody['occurred_at']);
    }

    private function event(
        string $orderId,
        string $routingKey = OutboundRoutingKeys::ORDER_CREATED,
        string $storeId = 'store-1',
    ): EventInterface {
        return new OrderTestEvent($routingKey, [
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);
    }
}

final readonly class OrderTestEvent implements EventInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private string $routingKey,
        private array $payload,
    ) {
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
