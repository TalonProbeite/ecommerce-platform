<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;

interface StockReservationRepositoryInterface
{
    /**
     * Undocumented function
     *
     * @param string $orderID
     * @return Collection<int, StockReservation>
     */
    public function findByOrderId(string $orderID): Collection;
    /**
     * Undocumented function
     *
     * @param string $productID
     * @param CarbonImmutable $start
     * @param CarbonImmutable $end
     * @return Collection<int,StockReservation>
     */
    public function findActiveByProductAndPeriod(
        string $productID,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection;

    public function save(StockReservation $stockReservation): StockReservation;
}
