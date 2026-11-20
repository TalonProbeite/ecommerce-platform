<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;
use Throwable;

final class InvalidWebhookSignature extends RuntimeException
{
    public function __construct(string $message = 'The webhook signature is invalid.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
