<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Infrastructure\Messaging\Inbound\Data\ProductDeletedData;
use App\Infrastructure\Messaging\Inbound\Data\ProductUpsertData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\Support\MessagingContract;
use Tests\TestCase;

final class ProductPayloadContractTest extends TestCase
{
    #[Test]
    public function product_upsert_fields_match_the_messaging_contract(): void
    {
        $section = $this->contractSection(
            '### `product.created` / `product.updated`',
            '### `product.deleted`',
        );

        self::assertSame(
            $this->expectedFields($section),
            $this->dtoFields(ProductUpsertData::class),
        );
    }

    #[Test]
    public function product_deleted_fields_match_the_messaging_contract(): void
    {
        $section = $this->contractSection(
            '### `product.deleted`',
            '### `stock.changed`',
        );

        self::assertSame(
            $this->expectedFields($section),
            $this->dtoFields(ProductDeletedData::class),
        );
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
