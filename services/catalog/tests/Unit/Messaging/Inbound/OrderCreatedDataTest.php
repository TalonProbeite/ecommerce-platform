<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Messaging\Inbound\Data\OrderCreatedItemData;
use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Messaging\Inbound\InboundRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderCreatedDataTest extends TestCase
{
    #[Test]
    public function it_builds_a_typed_dto_from_a_valid_payload(): void
    {
        $data = OrderCreatedData::fromPayload(self::payload());

        self::assertSame('7c9e6679-7425-40de-944b-e07fc1f90ae7', $data->eventId);
        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $data->orderId);
        self::assertSame('3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f', $data->storeId);
        self::assertInstanceOf(CarbonImmutable::class, $data->occurredAt);
        self::assertCount(1, $data->items);
        self::assertEquals(
            [
                new OrderCreatedItemData(
                    '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                    2,
                    CarbonImmutable::parse('2026-11-21T09:00:00+00:00'),
                    CarbonImmutable::parse('2026-11-23T18:00:00+00:00'),
                ),
            ],
            $data->items,
        );

        $item = $data->items[0];
        self::assertSame('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', $item->productId);
        self::assertSame(2, $item->quantity);
        self::assertTrue($item->rentalStartAt->equalTo(CarbonImmutable::parse('2026-11-21T09:00:00+00:00')));
        self::assertTrue($item->rentalEndAt->equalTo(CarbonImmutable::parse('2026-11-23T18:00:00+00:00')));
    }

    #[Test]
    public function it_preserves_the_offset_of_parsed_dates(): void
    {
        $payload = self::payload([
            'occurred_at' => '2026-11-20T13:00:00+03:00',
            'items' => [
                [
                    'rental_start_at' => '2026-11-21T12:00:00+03:00',
                    'rental_end_at' => '2026-11-23T21:00:00+03:00',
                ],
            ],
        ]);

        $data = OrderCreatedData::fromPayload($payload);
        $item = $data->items[0];

        self::assertSame('+03:00', $data->occurredAt->getTimezone()->getName());
        self::assertSame('+03:00', $item->rentalStartAt->getTimezone()->getName());
        self::assertTrue($data->occurredAt->equalTo(CarbonImmutable::parse('2026-11-20T10:00:00+00:00')));
        self::assertTrue($item->rentalStartAt->equalTo(CarbonImmutable::parse('2026-11-21T09:00:00+00:00')));
    }

    #[Test]
    public function it_rejects_a_payload_without_event_id(): void
    {
        $payload = self::payload();
        unset($payload['event_id']);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('event_id', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_non_uuid_order_id(): void
    {
        $exception = self::validationError(self::payload(['order_id' => 'not-a-uuid']));

        self::assertArrayHasKey('order_id', $exception->errors);
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
    public function it_rejects_more_items_than_the_contract_allows(): void
    {
        $items = [];

        for ($index = 0; $index <= InboundRules::MAX_ITEMS; $index++) {
            $items[] = self::item();
        }

        $exception = self::validationError(self::payload(['items' => $items]));

        self::assertArrayHasKey('items', $exception->errors);
    }

    /**
     * @param int|string $quantity
     */
    #[Test]
    #[DataProvider('invalidQuantities')]
    public function it_rejects_invalid_quantities(int|string $quantity): void
    {
        $payload = self::payload([
            'items' => [
                ['quantity' => $quantity],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.quantity', $exception->errors);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above maximum' => [InboundRules::MAX_QUANTITY + 1];
        yield 'not a number' => ['abc'];
    }

    #[Test]
    public function it_rejects_a_rental_window_that_ends_before_it_starts(): void
    {
        $payload = self::payload([
            'items' => [
                [
                    'rental_start_at' => '2026-11-23T18:00:00+00:00',
                    'rental_end_at' => '2026-11-21T09:00:00+00:00',
                ],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.rental_end_at', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_rental_window_that_starts_and_ends_at_the_same_moment(): void
    {
        $payload = self::payload([
            'items' => [
                [
                    'rental_start_at' => '2026-11-21T09:00:00+00:00',
                    'rental_end_at' => '2026-11-21T09:00:00+00:00',
                ],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.rental_end_at', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_timestamp_without_an_offset(): void
    {
        $payload = self::payload([
            'items' => [
                ['rental_start_at' => '2026-11-21T09:00:00'],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.rental_start_at', $exception->errors);
    }

    #[Test]
    public function it_rejects_a_zulu_timestamp_because_the_contract_requires_an_offset(): void
    {
        $exception = self::validationError(self::payload(['occurred_at' => '2026-11-20T10:00:00Z']));

        self::assertArrayHasKey('occurred_at', $exception->errors);
    }

    #[Test]
    public function it_does_not_run_the_window_check_when_a_date_is_malformed(): void
    {
        $payload = self::payload([
            'items' => [
                [
                    'rental_start_at' => 'not-a-date',
                    'rental_end_at' => '2026-11-23T18:00:00+00:00',
                ],
            ],
        ]);

        $exception = self::validationError($payload);

        self::assertArrayHasKey('items.0.rental_start_at', $exception->errors);
        self::assertArrayNotHasKey('items.0.rental_end_at', $exception->errors);
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
            'product_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            'quantity' => 2,
            'rental_start_at' => '2026-11-21T09:00:00+00:00',
            'rental_end_at' => '2026-11-23T18:00:00+00:00',
        ];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function validationError(array $payload): InvalidMessageException
    {
        try {
            OrderCreatedData::fromPayload($payload);
        } catch (InvalidMessageException $exception) {
            return $exception;
        }

        self::fail('Expected InvalidMessageException was not thrown.');
    }
}
