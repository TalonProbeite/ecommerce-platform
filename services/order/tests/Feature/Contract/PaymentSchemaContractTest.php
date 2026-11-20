<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Infrastructure\Persistence\SchemaValues;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

final class PaymentSchemaContractTest extends TestCase
{
    #[Test]
    public function payment_statuses_match_schema_values_and_the_check_constraint(): void
    {
        self::assertSame(SchemaValues::PAYMENT_STATUS, PaymentStatus::all());
        self::assertContains('status', $this->publicProperties(PaymentRecord::class));

        $migration = (string) file_get_contents(
            database_path('migrations/2026_12_01_100003_create_payments_table.php'),
        );

        self::assertStringContainsString(SchemaValues::quotedList(SchemaValues::PAYMENT_STATUS), $migration);
    }

    #[Test]
    public function refund_statuses_and_reasons_match_schema_values_and_the_check_constraints(): void
    {
        self::assertSame(SchemaValues::REFUND_STATUS, RefundStatus::all());

        $properties = $this->publicProperties(RefundRecord::class);

        self::assertContains('status', $properties);
        self::assertContains('reason', $properties);

        $migration = (string) file_get_contents(
            database_path('migrations/2026_12_01_100004_create_refunds_table.php'),
        );

        self::assertStringContainsString(SchemaValues::quotedList(SchemaValues::REFUND_STATUS), $migration);
        self::assertStringContainsString(SchemaValues::quotedList(SchemaValues::REFUND_REASON), $migration);
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function publicProperties(string $class): array
    {
        return array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
        );
    }
}
