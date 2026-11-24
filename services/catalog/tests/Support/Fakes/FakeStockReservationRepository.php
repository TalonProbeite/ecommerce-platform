<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Enums\ReservationStatus;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;

final class FakeStockReservationRepository implements StockReservationRepositoryInterface
{
    /**
     * @var array<string, StockReservation>
     */
    private array $reservations = [];

    public function seed(
        string $lineId,
        string $orderId,
        string $productId,
        string $storeId,
        int $quantity,
        ReservationStatus $status = ReservationStatus::Reserved,
        ?CarbonImmutable $rentalStartAt = null,
        ?CarbonImmutable $rentalEndAt = null,
    ): StockReservation {
        $reservation = new StockReservation();
        $reservation->forceFill([
            'id' => (string) Uuid::uuid7(),
            'order_line_id' => $lineId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'status' => $status->value,
            'rental_start_at' => $rentalStartAt,
            'rental_end_at' => $rentalEndAt,
        ]);

        $this->reservations[$lineId] = $reservation;

        return $reservation;
    }

    public function get(string $lineId): ?StockReservation
    {
        return $this->reservations[$lineId] ?? null;
    }

    /**
     * @return list<StockReservation>
     */
    public function all(): array
    {
        return array_values($this->reservations);
    }

    public function findByOrderId(string $orderID): Collection
    {
        return new Collection(array_values(array_filter(
            $this->reservations,
            static fn (StockReservation $reservation): bool => $reservation->order_id === $orderID,
        )));
    }

    public function findById(string $id): ?StockReservation
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->id === $id) {
                return $reservation;
            }
        }

        return null;
    }

    public function findActiveByStore(string $storeID, ?string $status = null): Collection
    {
        return new Collection(array_values(array_filter(
            $this->reservations,
            static fn (StockReservation $reservation): bool => $reservation->store_id === $storeID
                && in_array($reservation->status, ['reserved', 'issued'], true)
                && ($status === null || $reservation->status === $status),
        )));
    }

    public function hasActiveByStore(string $storeId): bool
    {
        return $this->findActiveByStore($storeId)->isNotEmpty();
    }

    public function hasActiveByProduct(string $productId): bool
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->product_id === $productId
                && in_array($reservation->status, ['reserved', 'issued'], true)) {
                return true;
            }
        }

        return false;
    }

    public function save(StockReservation $stockReservation): StockReservation
    {
        if ($stockReservation->id === null) {
            $stockReservation->id = (string) Uuid::uuid7();
        }

        $this->reservations[(string) $stockReservation->order_line_id] = $stockReservation;

        return $stockReservation;
    }

    /**
     * @param list<array{
     *   order_line_id: string, order_id: string, product_id: string,
     *   store_id: string, quantity: int, status: string,
     *   rental_start_at: ?string, rental_end_at: ?string
     * }> $rows
     */
    public function insertIgnoringExisting(array $rows): int
    {
        $inserted = 0;

        foreach ($rows as $row) {
            if (isset($this->reservations[$row['order_line_id']])) {
                continue;
            }

            $reservation = new StockReservation();
            $reservation->forceFill([
                'id' => (string) Uuid::uuid7(),
                'order_line_id' => $row['order_line_id'],
                'order_id' => $row['order_id'],
                'product_id' => $row['product_id'],
                'store_id' => $row['store_id'],
                'quantity' => $row['quantity'],
                'status' => $row['status'],
                'rental_start_at' => $row['rental_start_at'],
                'rental_end_at' => $row['rental_end_at'],
            ]);

            $this->reservations[$row['order_line_id']] = $reservation;
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @param list<string> $lineIds
     * @return list<string>
     */
    public function findExistingLineIds(array $lineIds): array
    {
        return array_values(array_filter(
            $lineIds,
            fn (string $lineId): bool => isset($this->reservations[$lineId]),
        ));
    }

    public function findByLineIdForUpdate(string $lineId): ?StockReservation
    {
        return $this->get($lineId);
    }

    public function transitionStatus(
        string $lineId,
        ReservationStatus $from,
        ReservationStatus $to,
        CarbonImmutable $at,
    ): int {
        $reservation = $this->reservations[$lineId] ?? null;

        if ($reservation === null || $reservation->status !== $from->value) {
            return 0;
        }

        $reservation->status = $to->value;
        $reservation->updated_at = $at;

        if ($to === ReservationStatus::Issued) {
            $reservation->issued_at = $at;
        }

        if ($to === ReservationStatus::Returned) {
            $reservation->returned_at = $at;
        }

        return 1;
    }
}
