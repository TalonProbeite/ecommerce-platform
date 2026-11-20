<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

final class ReturnBeforeRentalStart extends DomainException
{
    public function __construct(
        public readonly string $itemId,
        public readonly CarbonImmutable $rentalStartAt,
    ) {
        parent::__construct(sprintf(
            'Order item "%s" cannot be returned before its rental starts at "%s".',
            $itemId,
            $rentalStartAt->utc()->format('Y-m-d\TH:i:s.uP'),
        ));
    }
}
