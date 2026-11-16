<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Query\ListOrders\OrderListCriteria;
use App\Application\Query\ListOrders\OrderListResult;

interface OrderReadRepository
{
    public function list(OrderListCriteria $criteria): OrderListResult;
}
