<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Payments\Stripe;

use App\Application\Payments\RefundStatus;
use App\Infrastructure\Payments\Stripe\RefundStatusMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\RecordingLogger;

final class RefundStatusMapperTest extends TestCase
{
    #[Test]
    public function it_maps_known_stripe_statuses(): void
    {
        $mapper = new RefundStatusMapper(new RecordingLogger());

        self::assertSame(RefundStatus::PENDING, $mapper->map('pending', 're_1'));
        self::assertSame(RefundStatus::PENDING, $mapper->map('requires_action', 're_1'));
        self::assertSame(RefundStatus::SUCCEEDED, $mapper->map('succeeded', 're_1'));
        self::assertSame(RefundStatus::FAILED, $mapper->map('failed', 're_1'));
        self::assertSame(RefundStatus::CANCELED, $mapper->map('canceled', 're_1'));
    }

    #[Test]
    public function it_treats_an_unknown_status_as_failed_and_logs_critical(): void
    {
        $logger = new RecordingLogger();
        $mapper = new RefundStatusMapper($logger);

        $status = $mapper->map('something_new', 're_1');

        self::assertSame(RefundStatus::FAILED, $status);

        $records = $logger->records();

        self::assertCount(1, $records);
        self::assertSame('critical', $records[0]['level']);
        self::assertSame('re_1', $records[0]['context']['refund_id']);
        self::assertSame('something_new', $records[0]['context']['status']);
    }
}
