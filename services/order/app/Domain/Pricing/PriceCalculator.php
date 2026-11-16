<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;
use InvalidArgumentException;

final class PriceCalculator
{
    /**
     * @param list<PriceLine> $lines
     */
    public function calculate(array $lines): PriceCalculationResult
    {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one order item is required to calculate prices.');
        }

        $items = [];
        $subtotal = Money::zero();
        $deposit = Money::zero();

        foreach ($lines as $line) {
            $billedHours = $line->rentalPeriod->billedHours();
            $lineTotal = $line->pricePerHour->multiply($billedHours)->multiply($line->quantity);
            $depositTotal = $line->depositPerUnit->multiply($line->quantity);

            $items[] = new PricedItem(
                storeId: $line->storeId,
                productId: $line->productId,
                productName: $line->productName,
                quantity: $line->quantity,
                rentalPeriod: $line->rentalPeriod,
                pricePerHour: $line->pricePerHour,
                depositPerUnit: $line->depositPerUnit,
                billedHours: $billedHours,
                lineTotal: $lineTotal,
                depositTotal: $depositTotal,
            );

            $subtotal = $subtotal->add($lineTotal);
            $deposit = $deposit->add($depositTotal);
        }

        return new PriceCalculationResult($items, $subtotal, $deposit);
    }
}
