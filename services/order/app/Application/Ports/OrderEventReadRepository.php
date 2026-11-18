<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Query\ListOrderEvents\ListOrderEventsCriteria;
use App\Application\Query\ListOrderEvents\OrderEventPage;

interface OrderEventReadRepository
{
    public function orderExists(string $orderId): bool;

    public function list(ListOrderEventsCriteria $criteria): OrderEventPage;
}
