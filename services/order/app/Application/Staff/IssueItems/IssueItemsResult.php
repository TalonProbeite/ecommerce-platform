<?php

declare(strict_types=1);

namespace App\Application\Staff\IssueItems;

use App\Domain\Order\Order;

final readonly class IssueItemsResult
{
    /**
     * @param list<string> $processedItemIds
     * @param list<string> $skippedItemIds
     */
    public function __construct(
        public Order $order,
        public array $processedItemIds,
        public array $skippedItemIds,
    ) {
    }
}
