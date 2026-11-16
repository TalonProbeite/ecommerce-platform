<?php

declare(strict_types=1);

namespace App\Application\Customer\CreateOrder;

use App\Domain\Order\Order;

final readonly class CreateOrderResult
{
    public function __construct(
        public Order $order,
        public bool $created,
    ) {
    }
}
