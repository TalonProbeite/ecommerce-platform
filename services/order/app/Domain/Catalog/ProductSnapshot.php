<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Money\Money;
use Carbon\CarbonImmutable;

final readonly class ProductSnapshot
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public string $categoryId,
        public string $name,
        public Money $pricePerHour,
        public Money $depositPerUnit,
        public bool $isActive,
        public CarbonImmutable $sourceOccurredAt,
        public ?CarbonImmutable $deletedAt = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->isActive && $this->deletedAt === null;
    }

    public function sourceOccurredAt(): CarbonImmutable
    {
        return $this->sourceOccurredAt;
    }

    public function deletedAt(): ?CarbonImmutable
    {
        return $this->deletedAt;
    }
}
