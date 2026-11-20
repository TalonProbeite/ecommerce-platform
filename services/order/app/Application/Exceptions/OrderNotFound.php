<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

final class OrderNotFound extends RuntimeException
{
    public function __construct(public readonly string $orderId)
    {
        parent::__construct(sprintf('Order "%s" does not exist.', $orderId));
    }
}
