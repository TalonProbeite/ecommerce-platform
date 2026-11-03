<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Collection;

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
     * Save a stock reservation.
     */
    public function save(StockReservation $stockReservation): StockReservation
    {
        $stockReservation->saveOrFail();
        return $stockReservation;
    }
}
