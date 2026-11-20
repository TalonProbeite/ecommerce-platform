<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class InvalidQuantity extends DomainException
{
    public function __construct(public readonly int $quantity)
    {
        parent::__construct(sprintf(
            'Order item quantity must be between 1 and 1000, got %d.',
            $quantity,
        ));
    }
}
