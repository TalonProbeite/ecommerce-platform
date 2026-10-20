<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Collection;

class StockReservationRepository implements StockReservationRepositoryInterface
{
    public function findByOrderId(string $orderID): Collection
    {
        return StockReservation::where('order_id', $orderID)
            ->get();
    }

    public function findById(string $id): ?StockReservation
    {
        return StockReservation::find($id);
    }

    public function findActiveByStore(string $storeID, ?string $status = null): Collection
    {
        return StockReservation::where('store_id', $storeID)
            ->whereIn('status', ['reserved', 'issued'])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->get();
    }

    public function save(StockReservation $stockReservation): StockReservation
    {
        $stockReservation->saveOrFail();
        return $stockReservation;
    }
}
