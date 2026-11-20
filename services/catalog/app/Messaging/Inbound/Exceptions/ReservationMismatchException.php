<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

/**
 * Permanent failure: the stored reservation line does not describe the same
 * order, store, product, or quantity as the incoming lifecycle event.
 * This is a data mismatch, not a status transition, so retrying cannot help.
 */
final class ReservationMismatchException extends InboundMessageException
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public readonly string $lineId,
        public readonly array $fields,
    ) {
        parent::__construct(sprintf(
            'Reservation identity mismatch for order line "%s": %s.',
            $lineId,
            implode(', ', $fields),
        ));
    }
}
