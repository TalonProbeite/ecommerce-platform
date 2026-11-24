<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class PaymentInProgress extends RuntimeException
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $providerPaymentId,
    ) {
        parent::__construct(sprintf(
            'Payment "%s" for order "%s" is still processing; retry later.',
            $providerPaymentId,
            $orderId,
        ));
    }
}
