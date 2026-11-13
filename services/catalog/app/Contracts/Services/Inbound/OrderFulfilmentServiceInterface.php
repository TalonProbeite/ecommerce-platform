<?php

declare(strict_types=1);

namespace App\Contracts\Services\Inbound;

use App\Messaging\Inbound\Data\OrderIssuedData;
use App\Messaging\Inbound\Data\OrderReturnedData;
use App\Services\Inbound\Results\ProcessingResult;
use Carbon\CarbonImmutable;

interface OrderFulfilmentServiceInterface
{
    /**
     * Move reserved lines to issued. A missing reservation is retryable.
     */
    public function handleIssued(OrderIssuedData $data, CarbonImmutable $now): ProcessingResult;

    /**
     * Move issued lines to returned and release their reserved stock.
     */
    public function handleReturned(OrderReturnedData $data, CarbonImmutable $now): ProcessingResult;
}
