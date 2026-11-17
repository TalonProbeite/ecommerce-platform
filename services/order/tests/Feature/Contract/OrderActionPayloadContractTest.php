<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Application\Payments\RefundStatus;
use App\Domain\Events\OrderCancelled;
use App\Domain\Events\OrderEvent;
use App\Domain\Events\OrderIssued;
use App\Domain\Events\OrderReturned;
use App\Domain\Order\ActorRole;
use App\Domain\Order\CancelReason;
use App\Domain\Order\OrderStatus;
use App\Infrastructure\Persistence\SchemaValues;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MessagingContract;
use Tests\TestCase;

final class OrderActionPayloadContractTest extends TestCase
{
    #[Test]
    public function cancelled_issued_and_returned_payloads_match_the_messaging_contract(): void
    {
        $section = $this->contractSection(
            '### `order.cancelled`, `order.issued`, `order.returned`',
            '### `order.paid`',
        );

        self::assertSame(1, preg_match('/```json\s*(.*?)```/s', $section, $matches));

        /** @var array<string, mixed> $example */
        $example = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $items */
        $items = $example['items'];
        $expected = [
            'order_id' => $example['order_id'],
            'store_id' => $example['store_id'],
            'items' => $items,
        ];

        $events = [
            new OrderCancelled(
                orderId: (string) $example['order_id'],
                storeId: (string) $example['store_id'],
                items: $items,
                fromStatus: OrderStatus::PAID,
                actorRole: ActorRole::ADMIN,
            ),
            new OrderIssued(
                orderId: (string) $example['order_id'],
                storeId: (string) $example['store_id'],
                items: $items,
                fromStatus: OrderStatus::PAID,
                toStatus: OrderStatus::IN_RENTAL,
            ),
            new OrderReturned(
                orderId: (string) $example['order_id'],
                storeId: (string) $example['store_id'],
                items: $items,
                fromStatus: OrderStatus::IN_RENTAL,
                toStatus: OrderStatus::COMPLETED,
            ),
        ];

        foreach ($events as $event) {
            self::assertInstanceOf(OrderEvent::class, $event);
            self::assertSame($expected, $event->toArray());
        }
    }

    #[Test]
    public function cancel_reasons_and_refund_values_match_schema_values(): void
    {
        self::assertSame(
            SchemaValues::CANCEL_REASON,
            array_map(static fn (CancelReason $reason): string => $reason->value, CancelReason::cases()),
        );
        self::assertSame(SchemaValues::REFUND_STATUS, RefundStatus::all());
        self::assertSame(['order_cancelled', 'item_returned'], SchemaValues::REFUND_REASON);
    }

    private function contractSection(string $heading, string $nextHeading): string
    {
        $document = (string) file_get_contents(MessagingContract::path());
        $start = strpos($document, $heading);
        self::assertNotFalse($start, sprintf('Heading "%s" was not found in the messaging contract.', $heading));

        $end = strpos($document, $nextHeading, $start);
        self::assertNotFalse($end, sprintf('Heading "%s" was not found in the messaging contract.', $nextHeading));

        return substr($document, $start, $end - $start);
    }
}
