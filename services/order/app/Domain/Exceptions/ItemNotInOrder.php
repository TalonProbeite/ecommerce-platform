<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class ItemNotInOrder extends DomainException
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $itemId,
    ) {
        parent::__construct(sprintf(
            'Order item "%s" does not belong to order "%s".',
            $itemId,
            $orderId,
        ));
    }
}
