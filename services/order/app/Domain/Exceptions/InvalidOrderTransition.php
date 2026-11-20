<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Order\OrderStatus;
use DomainException;

final class InvalidOrderTransition extends DomainException
{
    public function __construct(
        public readonly OrderStatus $currentStatus,
        public readonly OrderStatus $requestedStatus,
    ) {
        parent::__construct(sprintf(
            'Order transition from "%s" to "%s" is not allowed.',
            $currentStatus->value,
            $requestedStatus->value,
        ));
    }
}
