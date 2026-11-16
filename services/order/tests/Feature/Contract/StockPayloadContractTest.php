<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Infrastructure\Messaging\Inbound\Data\StockReservationFailedData;
use App\Infrastructure\Messaging\Inbound\Data\StockReservedData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

final class StockPayloadContractTest extends TestCase
{
    #[Test]
    public function stock_reserved_fields_match_the_messaging_contract(): void
    {
        $section = $this->contractSection(
            '### `stock.reserved`',
            '### `stock.reservation_failed`',
        );

        self::assertSame(
            $this->expectedFields($section),
            $this->dtoFields(StockReservedData::class),
        );
    }

    #[Test]
    public function stock_reservation_failed_fields_match_the_messaging_contract(): void
    {
        $section = $this->contractSection(
            '### `stock.reservation_failed`',
            '### `product.created` / `product.updated`',
        );

        self::assertSame(
            $this->expectedFields($section),
            $this->dtoFields(StockReservationFailedData::class),
        );
    }

    private function contractSection(string $heading, string $nextHeading): string
    {
        $path = dirname(__DIR__, 5) . '/docs/contracts/messaging.md';
        self::assertFileExists($path);

        $document = (string) file_get_contents($path);
        $start = strpos($document, $heading);
        self::assertNotFalse($start, sprintf('Heading "%s" was not found in the messaging contract.', $heading));

        $end = strpos($document, $nextHeading, $start);
        self::assertNotFalse($end, sprintf('Heading "%s" was not found in the messaging contract.', $nextHeading));

        return substr($document, $start, $end - $start);
    }

    /**
     * @return list<string>
     */
    private function expectedFields(string $section): array
    {
        self::assertSame(1, preg_match('/```json\s*(.*?)```/s', $section, $matches));

        /** @var array<string, mixed> $example */
        $example = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

        $fields = array_keys($example);
        $fields = array_values(array_filter(
            $fields,
            static fn (string $field): bool => $field !== 'schema_version',
        ));

        sort($fields);

        return $fields;
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function dtoFields(string $class): array
    {
        $fields = [];

        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fields[] = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()));
        }

        sort($fields);

        return $fields;
    }
}
