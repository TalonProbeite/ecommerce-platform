<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class PaymentNotFound extends RuntimeException
{
    public function __construct(public readonly string $providerPaymentId)
    {
        parent::__construct(sprintf('Payment "%s" does not exist.', $providerPaymentId));
    }
}
