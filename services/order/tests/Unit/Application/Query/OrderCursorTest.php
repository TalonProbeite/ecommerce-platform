<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Query;

use App\Application\Query\ListOrders\OrderCursor;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderCursorTest extends TestCase
{
    #[Test]
    public function it_round_trips_created_at_and_id(): void
    {
        $createdAt = CarbonImmutable::parse('2026-11-20T10:00:00.123456+00:00');
        $cursor = new OrderCursor($createdAt, '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e');

        $decoded = OrderCursor::decode($cursor->encode());

        self::assertNotNull($decoded);
        self::assertSame('018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e', $decoded->id);
        self::assertTrue($createdAt->equalTo($decoded->createdAt));
    }

    #[Test]
    public function it_encodes_as_url_safe_base64_without_padding(): void
    {
        $encoded = (new OrderCursor(
            CarbonImmutable::parse('2026-11-20T10:00:00.000000+00:00'),
            '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e',
        ))->encode();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encoded);
        self::assertStringNotContainsString('=', $encoded);
    }

    #[Test]
    public function it_accepts_padded_base64_input(): void
    {
        $cursor = new OrderCursor(
            CarbonImmutable::parse('2026-11-20T10:00:00.000000+00:00'),
            '018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e',
        );

        $encoded = $cursor->encode();
        $padding = strlen($encoded) % 4;
        $padded = $padding === 0 ? $encoded : $encoded . str_repeat('=', 4 - $padding);

        $decoded = OrderCursor::decode($padded);

        self::assertNotNull($decoded);
        self::assertSame($cursor->id, $decoded->id);
    }

    #[Test]
    public function it_returns_null_for_an_invalid_cursor(): void
    {
        $invalid = [
            'not-a-cursor',
            '',
            base64_encode('not-json'),
            base64_encode('{"created_at":"2026-11-20T10:00:00.000000+00:00"}'),
            base64_encode('{"id":"018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e"}'),
            base64_encode('{"created_at":"not-a-date","id":"018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e"}'),
            base64_encode('{"created_at":"2026-11-20T10:00:00.000000+00:00","id":""}'),
        ];

        foreach ($invalid as $value) {
            self::assertNull(OrderCursor::decode($value), sprintf('Cursor "%s" must be rejected.', $value));
        }
    }
}
