<?php

declare(strict_types=1);

namespace App\Messaging\Inbound\Data;

use Carbon\CarbonImmutable;

final readonly class OrderCreatedItemData
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public CarbonImmutable $rentalStartAt,
        public CarbonImmutable $rentalEndAt,
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
            rentalStartAt: CarbonImmutable::parse((string) $row['rental_start_at']),
            rentalEndAt: CarbonImmutable::parse((string) $row['rental_end_at']),
        );
    }
}
