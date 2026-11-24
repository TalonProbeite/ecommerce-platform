<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class IdempotencyLockConflict extends RuntimeException
{
    public function __construct(public readonly string $idempotencyKey)
    {
        parent::__construct(sprintf(
            'A concurrent request with idempotency key "%s" is already in progress.',
            $idempotencyKey,
        ));
    }
}
