<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;

final class MixedStoreOrder extends RuntimeException
{
    /**
     * @param list<string> $storeIds
     */
    public static function forOrder(string $orderId, array $storeIds): self
    {
        return new self(sprintf(
            'Order %s contains items from different stores: %s.',
            $orderId,
            implode(', ', array_unique($storeIds)),
        ));
    }
}
