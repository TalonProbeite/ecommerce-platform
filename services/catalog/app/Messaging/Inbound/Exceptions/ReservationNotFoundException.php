<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

/**
 * Retryable failure: the order line has no reservation yet.
 *
 * A lifecycle event (for example order.issued) can overtake order.created,
 * so the message should be retried later instead of being rejected.
 */
final class ReservationNotFoundException extends RetryableMessageException
{
    public function __construct(public readonly string $lineId)
    {
        parent::__construct(sprintf('Stock reservation not found for order line "%s".', $lineId));
    }
}
