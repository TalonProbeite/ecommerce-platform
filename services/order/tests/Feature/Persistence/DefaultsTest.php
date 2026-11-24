<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class DefaultsTest extends PersistenceTestCase
{
    #[Test]
    public function raw_order_insert_without_id_uses_a_version_seven_uuid(): void
    {
        $row = DB::selectOne(
            "INSERT INTO orders (
                customer_id, store_id, status, currency, subtotal_amount, deposit_amount,
                idempotency_key, request_fingerprint
            ) VALUES (?, ?, 'created', 'EUR', 0, 0, ?, ?)
            RETURNING id, version",
            [
                (string) Str::uuid7(),
                (string) Str::uuid7(),
                (string) Str::uuid7(),
                hash('sha256', (string) Str::uuid7()),
            ],
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $row->id,
        );
        self::assertSame(1, (int) $row->version);
    }

    #[Test]
    public function processed_event_without_an_event_id_is_rejected(): void
    {
        $this->assertRejected(
            fn () => DB::table('processed_events')->insert([
                'routing_key' => 'order.created',
                'processed_at' => '2026-12-01 10:00:00.123456+00',
            ]),
            '23502',
        );
    }
}
