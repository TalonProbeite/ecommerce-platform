<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class IdempotencyFingerprintMismatch extends RuntimeException
{
    public function __construct(public readonly string $idempotencyKey)
    {
        parent::__construct(sprintf(
            'The request body does not match the original request for idempotency key "%s".',
            $idempotencyKey,
        ));
    }
}
