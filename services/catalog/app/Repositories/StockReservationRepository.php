<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StockReservationRepositoryInterface;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;


class StockReservationRepository implements StockReservationRepositoryInterface
{
    public function findByOrderId(string $orderID): Collection
    {
        return StockReservation::where('order_id', $orderID)
            ->get();
    }

    public function findActiveByProductAndPeriod(
        string $productID,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        return StockReservation::where('product_id', $productID)
            ->where('status', 'reserved')
            ->where('rental_start_at', '<', $end)
            ->where('rental_end_at', '>', $start)
            ->get();
    }

    public function save(StockReservation $stockReservation): StockReservation
    {
        $stockReservation->saveOrFail();
        return $stockReservation;
    }
}
