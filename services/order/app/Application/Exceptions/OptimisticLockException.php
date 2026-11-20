<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class OptimisticLockException extends RuntimeException
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $expectedVersion,
        public readonly ?int $actualVersion = null,
    ) {
        parent::__construct(sprintf(
            'Order "%s" was modified concurrently (expected version %d%s).',
            $orderId,
            $expectedVersion,
            $actualVersion !== null ? sprintf(', actual version %d', $actualVersion) : '',
        ));
    }
}
