<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Order\ItemStatus;
use DomainException;

final class InvalidItemTransition extends DomainException
{
    public function __construct(
        public readonly string $itemId,
        public readonly ItemStatus $currentStatus,
        public readonly ItemStatus $requestedStatus,
    ) {
        parent::__construct(sprintf(
            'Order item "%s" cannot transition from "%s" to "%s".',
            $itemId,
            $currentStatus->value,
            $requestedStatus->value,
        ));
    }
}
