<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Domain\Events\OrderPaid;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MessagingContract;
use Tests\TestCase;

final class OrderPaidPayloadContractTest extends TestCase
{
    #[Test]
    public function order_paid_payload_matches_the_messaging_contract(): void
    {
        $section = $this->contractSection('### `order.paid`', '## Contract limits');

        self::assertSame(1, preg_match('/```json\s*(.*?)```/s', $section, $matches));

        /** @var array<string, mixed> $example */
        $example = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);
        $expected = $example;
        unset($expected['event_id'], $expected['occurred_at']);

        $event = new OrderPaid(
            orderId: (string) $example['order_id'],
            storeId: (string) $example['store_id'],
            customerId: (string) $example['customer_id'],
            amountTotal: (int) $example['amount_total'],
            paidAt: CarbonImmutable::parse((string) $example['paid_at']),
        );

        self::assertSame($expected, $event->toArray());
    }

    private function contractSection(string $heading, string $nextHeading): string
    {
        $path = MessagingContract::path();

        $document = (string) file_get_contents($path);
        $start = strpos($document, $heading);
        self::assertNotFalse($start, sprintf('Heading "%s" was not found in the messaging contract.', $heading));

        $end = strpos($document, $nextHeading, $start);
        self::assertNotFalse($end, sprintf('Heading "%s" was not found in the messaging contract.', $nextHeading));

        return substr($document, $start, $end - $start);
    }
}
