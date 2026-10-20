<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Stock;
use App\Models\StockReservation;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Available = quantity - reserved. Reserved includes reserved and issued units.
 * Issue leaves stocks.reserved unchanged; release and return decrease it once.
 * Passing rental_end_at never automatically frees stock. Quantity is the total fund.
 * All mutations enforce store ownership and stock invariants atomically; missing
 * entities, insufficient stock and invalid transitions are business-operation errors.
 * Order IDs are external UUIDs without an orders foreign key.
 */
interface StockServiceInterface
{
    public function get(string $productId, string $storeId): ?Stock;

    /** Reject negative quantity or quantity below the currently reserved count. */
    public function setQuantity(string $productId, string $storeId, int $quantity): Stock;

    /**
     * Reserve a positive quantity and increase stocks.reserved once.
     * The same orderId/productId and quantity is an idempotent repeat: return the
     * existing reservation without increasing reserved or reactivating a terminal
     * reservation. A different quantity is a conflict. Store ownership still applies.
     * Rental dates describe the rental period, not a calendar-based availability pool.
     */
    public function reserve(
        string $orderId,
        string $productId,
        string $storeId,
        int $quantity,
        ?DateTimeImmutable $rentalStartAt = null,
        ?DateTimeImmutable $rentalEndAt = null,
    ): StockReservation;

    /**
     * reserved -> released: decrease stocks.reserved by the reservation quantity.
     * released -> released is a safe repeat; every other source status is an error.
     */
    public function release(string $reservationId, string $storeId): StockReservation;

    /**
     * reserved -> issued: record physical issue without changing stocks.reserved.
     * issued -> issued is a safe repeat, preserving issued_at; other sources are errors.
     */
    public function issue(string $reservationId, string $storeId): StockReservation;

    /**
     * issued -> returned: record physical return and decrease stocks.reserved once.
     * returned -> returned is a safe repeat, preserving returned_at; other sources are errors.
     */
    public function returnEquipment(string $reservationId, string $storeId): StockReservation;

    /**
     * Active reservations only: null selects both reserved and issued.
     * Reject status values other than reserved or issued; no terminal history here.
     *
     * @param 'reserved'|'issued'|null $status
     * @return Collection<int, StockReservation>
     */
    public function listReservations(string $storeId, ?string $status = null): Collection;
}
