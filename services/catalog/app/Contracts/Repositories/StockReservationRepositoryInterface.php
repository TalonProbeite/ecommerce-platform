<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Collection;

interface StockReservationRepositoryInterface
{
    /**
     * @return Collection<int, StockReservation>
     */
    public function findByOrderId(string $orderID): Collection;

    public function findById(string $id): ?StockReservation;

    /**
     * @return Collection<int, StockReservation>
     */
    public function findActiveByStore(string $storeID, ?string $status = null): Collection;

    public function save(StockReservation $stockReservation): StockReservation;
}
