<?php

declare(strict_types=1);

namespace App\Application\Customer\CreateOrder;

use Carbon\CarbonImmutable;

final readonly class CreateOrderCommand
{
    /**
     * @param list<CreateOrderItemCommand> $items
     */
    public function __construct(
        public string $customerId,
        public string $idempotencyKey,
        public array $items,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromValidated(string $customerId, string $idempotencyKey, array $validated): self
    {
        $items = [];

        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = is_array($validated['items'] ?? null) ? array_values($validated['items']) : [];

        foreach ($rawItems as $item) {
            $items[] = new CreateOrderItemCommand(
                productId: (string) $item['product_id'],
                quantity: (int) $item['quantity'],
                rentalStartAt: CarbonImmutable::parse((string) $item['rental_start_at']),
                rentalEndAt: CarbonImmutable::parse((string) $item['rental_end_at']),
            );
        }

        return new self($customerId, $idempotencyKey, $items);
    }
}
