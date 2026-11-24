<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Exceptions;

/**
 * Permanent failure: the payload does not match the contract.
 * Retrying cannot help, so the message must be rejected to the DLQ.
 */
final class InvalidMessageException extends InboundMessageException
{
    /**
     * @param array<string, list<string>> $errors Field name => validation messages.
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Inbound message payload is invalid.',
    ) {
        parent::__construct($message);
    }
}
