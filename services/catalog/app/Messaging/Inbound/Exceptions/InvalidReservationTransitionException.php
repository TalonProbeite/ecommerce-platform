<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

use App\Enums\ReservationStatus;

/**
 * Permanent failure: the reservation is in a status that cannot move to the
 * requested one (for example a returned reservation cannot be cancelled).
 */
final class InvalidReservationTransitionException extends InboundMessageException
{
    public function __construct(
        public readonly string $lineId,
        public readonly ReservationStatus $currentStatus,
        public readonly ReservationStatus $expectedFrom,
        public readonly ReservationStatus $expectedTo,
    ) {
        parent::__construct(sprintf(
            'Invalid reservation transition for order line "%s": current status "%s", expected "%s" -> "%s".',
            $lineId,
            $currentStatus->value,
            $expectedFrom->value,
            $expectedTo->value,
        ));
    }
}
