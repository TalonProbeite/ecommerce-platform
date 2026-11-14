<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound\Exceptions;

use RuntimeException;

/**
 * Base class for all failures raised while handling an inbound message.
 * The router maps subclasses to ack/reject/retry decisions.
 */
abstract class InboundMessageException extends RuntimeException
{
}
