<?php

declare(strict_types=1);

namespace App\Application\Query\OverdueRentals;

use Carbon\CarbonImmutable;

final readonly class OverdueRentalCriteria
{
    public function __construct(
        public ?string $storeId,
        public CarbonImmutable $now,
    ) {
    }
}
