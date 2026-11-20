<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Enums\ReservationStatus;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

interface StockReservationRepositoryInterface
{
    /**
     * Find stock reservations by order ID.
     *
     * @return Collection<int, StockReservation>
     */
    public function findByOrderId(string $orderID): Collection;

    /**
     * Find a stock reservation by its ID.
     */
    public function findById(string $id): ?StockReservation;

    /**
     * Find active reservations for a given store, optionally filtered by status.
     *
     * @return Collection<int, StockReservation>
     */
    public function findActiveByStore(string $storeID, ?string $status = null): Collection;

    /**
     * Determine whether the store has any reserved or issued reservations.
     */
    public function hasActiveByStore(string $storeId): bool;

    /**
     * Determine whether the product has any reserved or issued reservations.
     */
    public function hasActiveByProduct(string $productId): bool;

    /**
     * Save a stock reservation.
     */
    public function save(StockReservation $stockReservation): StockReservation;

    /**
     * Insert reservation rows, silently skipping rows whose order_line_id already exists.
     *
     * @param list<array{
     *   order_line_id: string, order_id: string, product_id: string,
     *   store_id: string, quantity: int, status: string,
     *   rental_start_at: ?string, rental_end_at: ?string
     * }> $rows
     * @return int Number of rows actually inserted (conflicts are not counted).
     */
    public function insertIgnoringExisting(array $rows): int;

    /**
     * @param list<string> $lineIds
     * @return list<string> Subset of $lineIds that already exist.
     */
    public function findExistingLineIds(array $lineIds): array;

    /**
     * Lock an order line reservation for the rest of the current transaction.
     * Must be called inside a DB transaction.
     */
    public function findByLineIdForUpdate(string $lineId): ?StockReservation;

    /**
     * Conditional status transition: UPDATE ... WHERE order_line_id = ? AND status = ?.
     * Does not issue a SELECT first, so it is safe under concurrency.
     *
     * @return int Number of affected rows (0 or 1).
     */
    public function transitionStatus(
        string $lineId,
        ReservationStatus $from,
        ReservationStatus $to,
        CarbonImmutable $at,
    ): int;
}
