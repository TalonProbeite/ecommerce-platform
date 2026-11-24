<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;

final readonly class PriceCalculationResult
{
    /**
     * @param list<PricedItem> $items
     */
    public function __construct(
        public array $items,
        public Money $subtotal,
        public Money $deposit,
    ) {
    }

    public function total(): Money
    {
        return $this->subtotal->add($this->deposit);
    }
}
