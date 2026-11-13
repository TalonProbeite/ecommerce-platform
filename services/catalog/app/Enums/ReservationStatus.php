<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reservation lifecycle status. Values match the stock_reservations CHECK constraint.
 */
enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Issued = 'issued';
    case Released = 'released';
    case Returned = 'returned';
}
