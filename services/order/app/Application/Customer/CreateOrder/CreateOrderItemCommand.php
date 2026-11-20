<?php

declare(strict_types=1);

namespace App\Application\Customer\CreateOrder;

use Carbon\CarbonImmutable;

final readonly class CreateOrderItemCommand
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public CarbonImmutable $rentalStartAt,
        public CarbonImmutable $rentalEndAt,
    ) {
    }
}
