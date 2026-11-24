<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;
use Throwable;

final class PaymentProviderUnavailable extends RuntimeException
{
    public function __construct(string $message = 'The payment provider is unavailable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
