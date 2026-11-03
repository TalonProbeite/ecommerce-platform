<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\StockReservation;
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
     * Save a stock reservation.
     */
    public function save(StockReservation $stockReservation): StockReservation;
}
