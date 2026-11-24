<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class RefundExceedsPayment extends RuntimeException
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $requestedAmount,
        public readonly int $availableAmount,
    ) {
        parent::__construct(sprintf(
            'Refund of %d minor units for order "%s" exceeds the available %d minor units.',
            $requestedAmount,
            $orderId,
            $availableAmount,
        ));
    }
}
