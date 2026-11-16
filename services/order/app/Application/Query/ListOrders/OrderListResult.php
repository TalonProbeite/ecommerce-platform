<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrders;

use App\Domain\Order\Order;

final readonly class OrderListResult
{
    /**
     * @param list<Order> $orders
     */
    public function __construct(public array $orders)
    {
    }
}
