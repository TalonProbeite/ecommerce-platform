<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Exceptions\InvalidQuantity;
use App\Domain\Money\Money;
use App\Domain\Rental\RentalPeriod;
use InvalidArgumentException;

final readonly class PriceLine
{
    public function __construct(
        public string $storeId,
        public string $productId,
        public string $productName,
        public Money $pricePerHour,
        public Money $depositPerUnit,
        public int $quantity,
        public RentalPeriod $rentalPeriod,
    ) {
        if ($storeId === '') {
            throw new InvalidArgumentException('A price line must have a store id.');
        }

        if ($productId === '') {
            throw new InvalidArgumentException('A price line must have a product id.');
        }

        if ($productName === '') {
            throw new InvalidArgumentException('A price line must have a product name.');
        }

        if ($quantity < 1 || $quantity > 1000) {
            throw new InvalidQuantity($quantity);
        }
    }
}
