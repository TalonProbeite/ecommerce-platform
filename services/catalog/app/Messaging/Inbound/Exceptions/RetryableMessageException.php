<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

use Throwable;

/**
 * Transient failure: a deadlock, lost database connection, or other
 * condition that may succeed on a later delivery.
 *
 * Not final on purpose: specific retryable failures (for example a missing
 * reservation that may arrive later) extend it so the router can retry them.
 */
class RetryableMessageException extends InboundMessageException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
