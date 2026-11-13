<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Data;

final readonly class OrderLineData
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public string $lineId,
    ) {
    }

    /**
     * @param array<array-key, mixed> $row Already validated.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            productId: (string) $row['product_id'],
            quantity: (int) $row['quantity'],
            lineId: (string) $row['line_id'],
        );
    }
}
