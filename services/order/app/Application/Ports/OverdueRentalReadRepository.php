<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Query\OverdueRentals\OverdueRentalCriteria;
use App\Application\Query\OverdueRentals\OverdueRentalPage;

interface OverdueRentalReadRepository
{
    public function find(OverdueRentalCriteria $criteria, ?string $cursor, int $limit): OverdueRentalPage;
}
