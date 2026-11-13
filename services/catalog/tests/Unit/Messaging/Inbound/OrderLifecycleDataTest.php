<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Messaging\Inbound\Data\OrderCancelledData;
use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\Data\OrderLifecycleData;
use App\Messaging\Inbound\Data\OrderLineData;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

final class OrderLifecycleDataTest extends TestCase
{
    /**
     * @param class-string<OrderLifecycleData> $expectedClass
     */
    #[Test]
    #[DataProvider('lifecycleEvents')]
    public function it_builds_the_subclass_matching_the_routing_key(string $expectedClass): void
    {
        $data = $expectedClass::fromPayload(self::payload());

        self::assertSame($expectedClass, $data::class);
        self::assertInstanceOf(OrderLifecycleData::class, $data);
        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $data->eventId);
        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $data->orderId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $data->storeId);
        self::assertCount(1, $data->items);
        self::assertEquals(
            [new OrderLineData(
                '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                2,
                'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223',
            )],
            $data->items,
        );
        self::assertSame('a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223', $data->items[0]->lineId);
    }

    /**
     * @return iterable<string, array{class-string<OrderLifecycleData>}>
     */
    public static function lifecycleEvents(): iterable
    {
        yield 'order.cancelled' => [OrderCancelledData::class];
        yield 'order.issued' => [OrderIssuedData::class];
        yield 'order.returned' => [OrderReturnedData::class];
    }

    #[Test]
    public function it_rejects_a_payload_without_order_id(): void
    {
        $payload = self::payload();
        unset($payload['order_id']);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('order_id', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_payload_without_line_id(): void
    {
        $payload = self::payload();
        unset($payload['items'][0]['line_id']);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.line_id', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_non_uuid_line_id(): void
    {
        $payload = self::payload([
            'items' => [
                ['line_id' => 'not-a-uuid'],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.line_id', $exception->errors);
    }

    #[Test]
    public function it_rejects_duplicate_line_ids_inside_one_event(): void
    {
        $duplicate = 'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223';
        $item = self::item();
        $payload = self::payload([
            'items' => [
                $item,
                array_replace($item, ['line_id' => $duplicate]),
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.1.line_id', $exception->errors);
    }

    #[Test]
    public function it_rejects_empty_items(): void
    {
        $payload = self::payload();
        $payload['items'] = [];

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items', $exception->errors);
    }

    #[Test]
    public function its_state_cannot_be_mutated_after_construction(): void
    {
        $data = OrderCancelledData::fromPayload(self::payload());

        $this->expectException(Error::class);

        (new ReflectionProperty(OrderCancelledData::class, 'eventId'))->setValue($data, 'another-id');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'occurred_at' => '2026-11-20T10:00:00+00:00',
            'order_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'store_id' => '3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f',
            'items' => [
                self::item(),
            ],
        ], $overrides);
    }

    /**
     * @return array<string, int|string>
     */
    private static function item(): array
    {
        return [
            'line_id' => 'a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223',
            'product_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            'quantity' => 2,
        ];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function validationError(array $payload): InvalidMessageException
    {
        try {
            OrderCancelledData::fromPayload($payload);
        } catch (InvalidMessageException $exception) {
            return $exception;
        }

        self::fail('Expected InvalidMessageException was not thrown.');
    }
}
