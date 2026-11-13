<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging\Inbound;

use App\Enums\ReservationStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use App\Repositories\ProcessedEventRepository;
use App\Repositories\StockReservationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\PostgresTestCase;

final class InboundRepositoriesTest extends PostgresTestCase
{
    private Store $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Tools']);
        $category = Category::create([
            'store_id' => $this->store->id,
            'name' => 'Tools',
            'slug' => 'tools',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'slug' => 'drill',
            'price_per_hour' => 100,
        ]);
        Stock::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 10,
        ]);
    }

    #[Test]
    public function transition_status_returns_zero_and_keeps_the_row_for_a_wrong_from_status(): void
    {
        $repository = new StockReservationRepository();
        $lineId = (string) Uuid::uuid7();
        $repository->insertIgnoringExisting([$this->row($lineId)]);

        $affected = $repository->transitionStatus(
            $lineId,
            ReservationStatus::Issued,
            ReservationStatus::Returned,
            CarbonImmutable::parse('2026-11-20T10:00:00+00:00'),
        );

        self::assertSame(0, $affected);
        $reservation = StockReservation::query()->where('order_line_id', $lineId)->firstOrFail();
        self::assertSame(ReservationStatus::Reserved->value, $reservation->status);
        self::assertNull($reservation->returned_at);
    }

    #[Test]
    public function transition_status_updates_status_and_the_target_timestamp(): void
    {
        $repository = new StockReservationRepository();
        $lineId = (string) Uuid::uuid7();
        $repository->insertIgnoringExisting([$this->row($lineId)]);
        $at = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');

        self::assertSame(1, $repository->transitionStatus(
            $lineId,
            ReservationStatus::Reserved,
            ReservationStatus::Issued,
            $at,
        ));

        $reservation = StockReservation::query()->where('order_line_id', $lineId)->firstOrFail();
        self::assertSame(ReservationStatus::Issued->value, $reservation->status);
        self::assertTrue($reservation->issued_at?->equalTo($at));
    }

    #[Test]
    public function insert_ignoring_existing_returns_zero_on_a_duplicate_order_line(): void
    {
        $repository = new StockReservationRepository();
        $lineId = (string) Uuid::uuid7();

        self::assertSame(1, $repository->insertIgnoringExisting([$this->row($lineId)]));
        self::assertSame(0, $repository->insertIgnoringExisting([$this->row($lineId)]));
        self::assertSame(1, StockReservation::query()->where('order_line_id', $lineId)->count());
    }

    #[Test]
    public function the_same_order_and_product_may_appear_in_multiple_lines(): void
    {
        $repository = new StockReservationRepository();
        $orderId = (string) Uuid::uuid7();
        $first = (string) Uuid::uuid7();
        $second = (string) Uuid::uuid7();

        $inserted = $repository->insertIgnoringExisting([
            $this->row($first, $orderId),
            $this->row($second, $orderId),
        ]);

        self::assertSame(2, $inserted);
        self::assertSame(2, StockReservation::query()
            ->where('order_id', $orderId)
            ->where('product_id', $this->product->id)
            ->count());
    }

    #[Test]
    public function mark_processed_returns_false_for_a_repeated_event_id(): void
    {
        $repository = new ProcessedEventRepository();
        $eventId = (string) Uuid::uuid7();
        $at = CarbonImmutable::parse('2026-11-20T10:00:00+00:00');

        self::assertTrue($repository->markProcessed($eventId, 'order.created', $at));
        self::assertFalse($repository->markProcessed($eventId, 'order.created', $at->addMinute()));

        self::assertSame(1, DB::table('processed_events')->where('event_id', $eventId)->count());
        self::assertSame('order.created', DB::table('processed_events')->where('event_id', $eventId)->value('routing_key'));
    }

    #[Test]
    public function the_new_schema_has_order_line_id_and_processed_events(): void
    {
        self::assertTrue(Schema::hasTable('processed_events'));
        self::assertTrue(Schema::hasColumn('stock_reservations', 'order_line_id'));

        $indexes = Schema::getIndexes('stock_reservations');
        self::assertNotEmpty(array_filter(
            $indexes,
            static fn (array $index): bool => $index['columns'] === ['order_line_id'] && $index['unique'],
        ));
        self::assertEmpty(array_filter(
            $indexes,
            static fn (array $index): bool => $index['columns'] === ['order_id', 'product_id'],
        ));
    }

    /**
     * @return array{
     *   order_line_id: string, order_id: string, product_id: string,
     *   store_id: string, quantity: int, status: string,
     *   rental_start_at: ?string, rental_end_at: ?string
     * }
     */
    private function row(string $lineId, ?string $orderId = null): array
    {
        return [
            'order_line_id' => $lineId,
            'order_id' => $orderId ?? (string) Uuid::uuid7(),
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 2,
            'status' => ReservationStatus::Reserved->value,
            'rental_start_at' => '2026-11-21 09:00:00+00',
            'rental_end_at' => '2026-11-23 18:00:00+00',
        ];
    }
}
