<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

final class InvalidRentalPeriod extends DomainException
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        parent::__construct(sprintf(
            'Rental end "%s" must be later than rental start "%s".',
            $end->format('Y-m-d\TH:i:s.uP'),
            $start->format('Y-m-d\TH:i:s.uP'),
        ));
    }
}
