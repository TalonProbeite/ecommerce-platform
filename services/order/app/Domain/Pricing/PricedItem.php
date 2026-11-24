<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;
use App\Domain\Order\OrderItem;
use App\Domain\Rental\RentalPeriod;

final readonly class PricedItem
{
    public function __construct(
        public string $storeId,
        public string $productId,
        public string $productName,
        public int $quantity,
        public RentalPeriod $rentalPeriod,
        public Money $pricePerHour,
        public Money $depositPerUnit,
        public int $billedHours,
        public Money $lineTotal,
        public Money $depositTotal,
    ) {
    }

    public function toOrderItem(string $lineId): OrderItem
    {
        return OrderItem::create(
            lineId: $lineId,
            storeId: $this->storeId,
            productId: $this->productId,
            productName: $this->productName,
            quantity: $this->quantity,
            rentalPeriod: $this->rentalPeriod,
            pricePerHour: $this->pricePerHour,
            depositPerUnit: $this->depositPerUnit,
        );
    }
}
