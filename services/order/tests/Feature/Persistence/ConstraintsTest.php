<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class ConstraintsTest extends PersistenceTestCase
{
    #[Test]
    public function quantity_must_be_between_one_and_one_thousand(): void
    {
        $order = $this->newOrder();

        $this->assertRejected(fn () => $this->newOrderItem($order, ['quantity' => 0]), '23514');
        $this->assertRejected(fn () => $this->newOrderItem($order, ['quantity' => 1001]), '23514');
    }

    #[Test]
    public function rental_end_must_be_after_rental_start(): void
    {
        $order = $this->newOrder();
        $start = CarbonImmutable::parse('2026-12-02T10:00:00.123456+00:00');

        $this->assertRejected(fn () => $this->newOrderItem($order, [
            'rental_start_at' => $start,
            'rental_end_at' => $start,
        ]), '23514');
    }

    #[Test]
    public function order_status_must_match_the_contract(): void
    {
        $this->assertRejected(fn () => $this->newOrder(['status' => 'pending']), '23514');
    }

    #[Test]
    public function a_returned_item_must_have_been_issued(): void
    {
        $order = $this->newOrder();

        $this->assertRejected(fn () => $this->newOrderItem($order, [
            'item_status' => 'returned',
            'returned_at' => CarbonImmutable::parse('2026-12-02T12:00:00.123456+00:00'),
            'returned_by' => (string) Str::uuid7(),
        ]), '23514');
    }
}
