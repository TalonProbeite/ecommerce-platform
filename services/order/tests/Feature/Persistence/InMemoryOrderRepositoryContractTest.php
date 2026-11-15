<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Application\Ports\OrderRepository;
use Carbon\CarbonImmutable;
use Tests\Support\Fakes\FakeOutboxWriter;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\InMemoryOrderRepository;

final class InMemoryOrderRepositoryContractTest extends OrderRepositoryContractTest
{
    private FakeOutboxWriter $outbox;

    private FixedClock $clock;

    private InMemoryOrderRepository $repository;

    protected function setUp(): void
    {
        // The in-memory implementation deliberately does not need the PostgreSQL schema.
        $this->clock = new FixedClock(CarbonImmutable::parse('2026-11-20T12:00:00.123456+00:00'));
        $this->outbox = new FakeOutboxWriter();
        $this->repository = new InMemoryOrderRepository($this->outbox, $this->clock);
    }

    protected function tearDown(): void
    {
        // No database schema is created by this test case.
    }

    protected function repository(): OrderRepository
    {
        return $this->repository;
    }

    protected function assertJournal(string $orderId, array $expected): void
    {
        $actual = [];

        foreach ($this->repository->journal() as $change) {
            if ($change->orderId === $orderId) {
                $actual[] = [$change->fromStatus?->value, $change->toStatus->value];
            }
        }

        self::assertSame($expected, $actual);
    }

    protected function assertOutboxContains(string $routingKey, string $orderId): void
    {
        foreach ($this->outbox->enqueued as $entry) {
            $payload = $entry['event']->toArray();

            if ($entry['event']->routingKey() === $routingKey && ($payload['order_id'] ?? null) === $orderId) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Outbox does not contain %s for order %s.', $routingKey, $orderId));
    }
}
