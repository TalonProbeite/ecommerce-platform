<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

/**
 * Permanent failure: no handler is registered for the routing key.
 * This usually means a binding or configuration mismatch.
 */
final class UnknownRoutingKeyException extends InboundMessageException
{
    public function __construct(public readonly string $routingKey)
    {
        parent::__construct(sprintf('No inbound handler is registered for routing key "%s".', $routingKey));
    }
}
