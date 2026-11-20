<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Enums\ReservationStatus;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class StockReservationRepository implements StockReservationRepositoryInterface
{
    /**
     * Find stock reservations by order ID.
     *
     * @return Collection<int, StockReservation>
     */
    public function findByOrderId(string $orderID): Collection
    {
        return StockReservation::where('order_id', $orderID)
            ->get();
    }

    /**
     * Find a stock reservation by its ID.
     */
    public function findById(string $id): ?StockReservation
    {
        return StockReservation::find($id);
    }

    /**
     * Find active reservations for a given store, optionally filtered by status.
     *
     * @return Collection<int, StockReservation>
     */
    public function findActiveByStore(string $storeID, ?string $status = null): Collection
    {
        return StockReservation::where('store_id', $storeID)
            ->whereIn('status', ['reserved', 'issued'])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->get();
    }

    /**
     * Determine whether the store has any reserved or issued reservations.
     */
    public function hasActiveByStore(string $storeId): bool
    {
        return StockReservation::query()
            ->where('store_id', $storeId)
            ->whereIn('status', ['reserved', 'issued'])
            ->exists();
    }

    /**
     * Determine whether the product has any reserved or issued reservations.
     */
    public function hasActiveByProduct(string $productId): bool
    {
        return StockReservation::query()
            ->where('product_id', $productId)
            ->whereIn('status', ['reserved', 'issued'])
            ->exists();
    }

    /**
     * Save a stock reservation.
     */
    public function save(StockReservation $stockReservation): StockReservation
    {
        $stockReservation->saveOrFail();
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
        if ($rows === []) {
            return 0;
        }

        $prepared = array_map(
            static fn (array $row): array => ['id' => (string) Uuid::uuid7()] + $row,
            $rows,
        );

        return DB::table('stock_reservations')->insertOrIgnore($prepared);
    }

    /**
     * @param list<string> $lineIds
     * @return list<string>
     */
    public function findExistingLineIds(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }

        return array_values(StockReservation::query()
            ->whereIn('order_line_id', $lineIds)
            ->pluck('order_line_id')
            ->map(static fn (mixed $lineId): string => (string) $lineId)
            ->all());
    }

    public function findByLineIdForUpdate(string $lineId): ?StockReservation
    {
        return StockReservation::query()
            ->where('order_line_id', $lineId)
            ->lockForUpdate()
            ->first();
    }

    public function transitionStatus(
        string $lineId,
        ReservationStatus $from,
        ReservationStatus $to,
        CarbonImmutable $at,
    ): int {
        $formattedAt = $at->format('Y-m-d H:i:s.uP');

        $values = [
            'status' => $to->value,
            'updated_at' => $formattedAt,
        ];

        if ($to === ReservationStatus::Issued) {
            $values['issued_at'] = $formattedAt;
        }

        if ($to === ReservationStatus::Returned) {
            $values['returned_at'] = $formattedAt;
        }

        // Use the query builder: Eloquent would overwrite updated_at with now().
        return DB::table('stock_reservations')
            ->where('order_line_id', $lineId)
            ->where('status', $from->value)
            ->update($values);
    }
}
