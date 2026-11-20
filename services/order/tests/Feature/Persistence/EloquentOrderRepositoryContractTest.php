<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Application\Ports\OrderRepository;
use App\Infrastructure\Messaging\Outbox\OutboxRepository;
use App\Infrastructure\Messaging\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\EloquentOrderRepository;
use App\Infrastructure\Persistence\OrderMapper;
use App\Infrastructure\Persistence\Transactions\DbTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fakes\FixedClock;

final class EloquentOrderRepositoryContractTest extends OrderRepositoryContractTestCase
{
    private FixedClock $clock;

    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FixedClock(CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00'));

        $this->repository = new EloquentOrderRepository(
            new DbTransactionRunner(),
            new OutboxWriter(new OutboxRepository()),
            new OrderMapper($this->clock),
            $this->clock,
        );
    }

    protected function repository(): OrderRepository
    {
        return $this->repository;
    }

    /**
     * @param list<array{0: ?string, 1: string}> $expected
     */
    protected function assertJournal(string $orderId, array $expected): void
    {
        $actual = DB::table('order_events')
            ->where('order_id', $orderId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['from_status', 'to_status'])
            ->map(static fn (object $row): array => [$row->from_status, $row->to_status])
            ->all();

        self::assertSame($expected, $actual);
    }

    protected function assertOutboxContains(string $routingKey, string $orderId): void
    {
        $exists = DB::table('outbox')
            ->where('routing_key', $routingKey)
            ->where('payload->order_id', $orderId)
            ->exists();

        self::assertTrue(
            $exists,
            sprintf('Outbox does not contain %s for order %s.', $routingKey, $orderId),
        );
    }
}
