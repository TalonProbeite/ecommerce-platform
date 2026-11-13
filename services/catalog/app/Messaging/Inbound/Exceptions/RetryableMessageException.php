<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

use Throwable;

/**
 * Transient failure: a deadlock, lost database connection, or other
 * condition that may succeed on a later delivery.
 */
final class RetryableMessageException extends InboundMessageException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
