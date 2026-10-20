<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Messaging\EventInterface;
use App\Messaging\Events\CategoryCreated;
use App\Messaging\Events\CategoryUpdated;
use App\Messaging\Events\CategoryDeleted;
use App\Messaging\Events\ProductCreated;
use App\Messaging\Events\ProductUpdated;
use App\Messaging\Events\ProductDeleted;
use App\Messaging\Events\StockChanged;
use App\Messaging\Events\StockReserved;
use App\Messaging\Events\StockReservationFailed;
use App\Messaging\Events\StockReservationFailureReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventContractTest extends TestCase
{
    /** @return iterable<string, array{EventInterface, string, array<string, int|string|null>}> */
    public static function events(): iterable
    {
        foreach ([ProductCreated::class => 'product.created', ProductUpdated::class => 'product.updated'] as $class => $key) {
            foreach ([0, 12345] as $price) {
                yield $key . $price => [new $class('product', 'store', 'Дрель / "Pro"', $price), $key,
                    ['product_id' => 'product', 'store_id' => 'store', 'name' => 'Дрель / "Pro"', 'price_per_hour' => $price]];
            }
        }
        yield 'product.deleted' => [new ProductDeleted('product', 'store'), 'product.deleted', ['product_id' => 'product', 'store_id' => 'store']];
        foreach ([CategoryCreated::class => 'category.created', CategoryUpdated::class => 'category.updated'] as $class => $key) {
            foreach ([null, 'parent'] as $parent) {
                yield $key . ($parent ?? 'root') => [new $class('category', 'store', 'Инструменты', 'tools', $parent), $key,
                    ['category_id' => 'category', 'store_id' => 'store', 'name' => 'Инструменты', 'slug' => 'tools', 'parent_id' => $parent]];
            }
        }
        yield 'category.deleted' => [new CategoryDeleted('category', 'store'), 'category.deleted', ['category_id' => 'category', 'store_id' => 'store']];
        foreach ([[10, 3, 7], [10, 10, 0], [0, 0, 0]] as [$quantity, $reserved, $available]) {
            yield 'stock.changed' . $quantity . ':' . $reserved => [new StockChanged('product', 'store', $quantity, $reserved), 'stock.changed',
                ['product_id' => 'product', 'store_id' => 'store', 'quantity' => $quantity, 'reserved' => $reserved, 'available' => $available]];
        }
        yield 'stock.reserved' => [new StockReserved('order', 'store', 'product', 2), 'stock.reserved',
            ['order_id' => 'order', 'store_id' => 'store', 'product_id' => 'product', 'quantity' => 2]];
        foreach (['insufficient_stock' => StockReservationFailureReason::InsufficientStock,
            'product_not_found' => StockReservationFailureReason::ProductNotFound] as $value => $reason) {
            yield $value => [new StockReservationFailed('order', 'store', 'product', 2, $reason), 'stock.reservation_failed',
                ['order_id' => 'order', 'store_id' => 'store', 'product_id' => 'product', 'quantity' => 2, 'reason' => $value]];
        }
    }

    /** @param array<string, int|string|null> $payload */
    #[DataProvider('events')]
    public function test_event_contract(EventInterface $event, string $routingKey, array $payload): void
    {
        self::assertInstanceOf(EventInterface::class, $event);
        self::assertSame($routingKey, $event->routingKey());
        self::assertSame($payload, $event->toArray());
        self::assertSame($payload, json_decode(json_encode($event->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }
}
