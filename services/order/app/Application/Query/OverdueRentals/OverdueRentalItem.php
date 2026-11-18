<?php

declare(strict_types=1);

namespace App\Application\Query\OverdueRentals;

use Carbon\CarbonImmutable;

/**
 * A single overdue rental line.
 *
 * `overdueHours` is calculated by ListOverdueRentalsUseCase from `rentalEndAt`
 * and the current Clock time; read repositories construct rows with 0 and the
 * use case enriches them.
 */
final readonly class OverdueRentalItem
{
    public function __construct(
        public string $orderId,
        public string $itemId,
        public string $productId,
        public string $productName,
        public string $storeId,
        public string $customerId,
        public int $quantity,
        public CarbonImmutable $rentalEndAt,
        public int $overdueHours,
    ) {
    }

    public function withOverdueHours(int $overdueHours): self
    {
        return new self(
            orderId: $this->orderId,
            itemId: $this->itemId,
            productId: $this->productId,
            productName: $this->productName,
            storeId: $this->storeId,
            customerId: $this->customerId,
            quantity: $this->quantity,
            rentalEndAt: $this->rentalEndAt,
            overdueHours: $overdueHours,
        );
    }
}
