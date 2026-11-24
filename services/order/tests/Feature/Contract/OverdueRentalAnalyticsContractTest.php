<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Application\Query\OverdueRentals\OverdueRentalItem;
use App\Http\Resources\OverdueRentalResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class OverdueRentalAnalyticsContractTest extends TestCase
{
    private const string DOCUMENT = 'docs/order/api-analytics.md';

    /**
     * @return list<string>
     */
    private function fields(): array
    {
        return [
            'order_id',
            'item_id',
            'product_id',
            'product_name',
            'store_id',
            'customer_id',
            'quantity',
            'rental_end_at',
            'overdue_hours',
        ];
    }

    #[Test]
    public function resource_fields_match_the_documented_contract(): void
    {
        $document = $this->document();

        $item = new OverdueRentalItem(
            orderId: '77777777-7777-4777-8777-777777777771',
            itemId: '01930000-0000-7000-8000-000000000301',
            productId: '33333333-3333-4333-8333-333333333333',
            productName: 'Drill',
            storeId: '22222222-2222-4222-8222-222222222222',
            customerId: '11111111-1111-4111-8111-111111111111',
            quantity: 1,
            rentalEndAt: CarbonImmutable::parse('2026-12-02T10:00:00.123456+00:00'),
            overdueHours: 2,
        );

        $payload = (new OverdueRentalResource($item))->toArray(
            Request::create('/api/v1/analytics/overdue-rentals'),
        );

        self::assertSame($this->fields(), array_keys($payload));
        self::assertSame('2026-12-02T10:00:00.123456+00:00', $payload['rental_end_at']);

        foreach ($this->fields() as $field) {
            self::assertStringContainsString(
                '`' . $field . '`',
                $document,
                sprintf('The field %s is not documented in %s.', $field, self::DOCUMENT),
            );
        }
    }

    private function document(): string
    {
        $candidates = [
            dirname(__DIR__, 5) . '/' . self::DOCUMENT,
            '/docs/order/api-analytics.md',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return (string) file_get_contents($candidate);
            }
        }

        throw new RuntimeException('The analytics contract document was not found.');
    }
}
