<?php

declare(strict_types=1);

namespace App\Application\Customer\CancelOrder;

use App\Domain\Order\Order;

final readonly class CancelOrderResult
{
    public function __construct(
        public Order $order,
        public bool $changed,
    ) {
    }
}
