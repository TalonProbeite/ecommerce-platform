<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class IdempotencyKeyAlreadyExists extends RuntimeException
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $idempotencyKey,
    ) {
        parent::__construct(sprintf(
            'An order for customer "%s" and idempotency key "%s" already exists.',
            $customerId,
            $idempotencyKey,
        ));
    }
}
