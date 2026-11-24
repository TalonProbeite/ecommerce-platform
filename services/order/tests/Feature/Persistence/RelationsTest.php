<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Infrastructure\Persistence\Models\OrderModel;
use App\Infrastructure\Persistence\Models\PaymentModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class RelationsTest extends PersistenceTestCase
{
    #[Test]
    public function an_order_loads_its_items_and_payments(): void
    {
        $order = $this->newOrder();
        $firstItem = $this->newOrderItem($order);
        $secondItem = $this->newOrderItem($order, ['product_id' => (string) Str::uuid7()]);

        $payment = PaymentModel::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_' . Str::uuid7(),
            'amount' => 1000,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'succeeded_at' => CarbonImmutable::parse('2026-12-02T12:00:00.123456+00:00'),
        ]);

        $loaded = OrderModel::with(['items', 'payments'])->findOrFail($order->id);

        self::assertSame(2, $loaded->items()->count());
        self::assertSame(1, $loaded->payments()->count());
        self::assertTrue($loaded->items->contains('id', $firstItem->id));
        self::assertTrue($loaded->items->contains('id', $secondItem->id));
        self::assertTrue($loaded->payments->contains('id', $payment->id));
        self::assertTrue($firstItem->order->is($order));
        self::assertTrue($payment->order->is($order));
    }
}
