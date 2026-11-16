<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class StoreMismatch extends RuntimeException
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $expectedStoreId,
        public readonly string $actualStoreId,
    ) {
        parent::__construct(sprintf(
            'Order "%s" belongs to store "%s", but the event carries store "%s".',
            $orderId,
            $expectedStoreId,
            $actualStoreId,
        ));
    }
}
