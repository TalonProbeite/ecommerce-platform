<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Infrastructure\Persistence\Models\OutboxModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class RoundTripTest extends PersistenceTestCase
{
    #[Test]
    public function order_created_at_keeps_microseconds(): void
    {
        $createdAt = CarbonImmutable::parse('2026-12-01T10:00:00.123456+00:00');

        $order = $this->newOrder([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $fresh = $order->fresh();

        self::assertNotNull($fresh);
        self::assertSame(
            '2026-12-01 10:00:00.123456',
            $fresh->created_at->utc()->format('Y-m-d H:i:s.u'),
        );
    }

    #[Test]
    public function outbox_occurred_at_keeps_microseconds(): void
    {
        $occurredAt = CarbonImmutable::parse('2026-12-01T10:00:00.654321+00:00');

        $outbox = OutboxModel::create([
            'event_id' => (string) Str::uuid7(),
            'routing_key' => 'order.created',
            'aggregate_key' => (string) Str::uuid7(),
            'payload' => ['order_id' => (string) Str::uuid7()],
            'occurred_at' => $occurredAt,
            'attempts' => 0,
        ]);

        $fresh = $outbox->fresh();

        self::assertNotNull($fresh);
        self::assertSame(
            '2026-12-01 10:00:00.654321',
            $fresh->occurred_at->utc()->format('Y-m-d H:i:s.u'),
        );
    }
}
