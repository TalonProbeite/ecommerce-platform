<?php

declare(strict_types=1);

namespace App\Application\Query\OverdueRentals;

final readonly class OverdueRentalPage
{
    /**
     * @param list<OverdueRentalItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
