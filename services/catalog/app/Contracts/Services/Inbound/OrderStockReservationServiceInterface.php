<?php

declare(strict_types=1);

namespace App\Contracts\Services\Inbound;

use App\Messaging\Inbound\Data\OrderCancelledData;
use App\Messaging\Inbound\Data\OrderCreatedData;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;

interface OrderStockReservationServiceInterface
{
    /**
     * Reserve stock for a newly created order.
     *
     * Returns Rejected (instead of throwing) when stock is insufficient or a
     * product has no stock row. Duplicate events return Duplicate.
     */
    public function handleCreated(OrderCreatedData $data, CarbonImmutable $now): ProcessingResult;

    /**
     * Release reservations for a cancelled order.
     *
     * A missing reservation is stored as a released tombstone so a late
     * order.created cannot reserve stock for an already cancelled line.
     */
    public function handleCancelled(OrderCancelledData $data, CarbonImmutable $now): ProcessingResult;
}
