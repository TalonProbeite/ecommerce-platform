<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use App\Infrastructure\Persistence\Models\OrderModel;
use PHPUnit\Framework\Attributes\Test;

final class ModelIdTest extends PersistenceTestCase
{
    #[Test]
    public function order_without_an_id_gets_a_version_seven_uuid_on_save(): void
    {
        $order = $this->newOrder();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $order->id,
        );
        self::assertSame($order->id, OrderModel::findOrFail($order->id)->id);
    }

    #[Test]
    public function an_explicit_id_is_not_overwritten(): void
    {
        $id = '123e4567-e89b-42d3-a456-426614174000';

        $order = $this->newOrder(['id' => $id]);

        self::assertSame($id, $order->id);
        self::assertSame($id, OrderModel::findOrFail($id)->id);
    }
}
