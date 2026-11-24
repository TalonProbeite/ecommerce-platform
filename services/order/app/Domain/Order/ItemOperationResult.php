<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Money\Money;

final readonly class ItemOperationResult
{
    /**
     * @param list<string> $processedItemIds
     * @param list<string> $skippedItemIds
     */
    public function __construct(
        public array $processedItemIds,
        public array $skippedItemIds,
        public Money $depositReturned,
    ) {
    }
}
