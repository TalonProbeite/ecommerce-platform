<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Exceptions\InvalidItemTransition;
use App\Domain\Exceptions\InvalidQuantity;
use App\Domain\Money\Money;
use App\Domain\Rental\RentalPeriod;
use Carbon\CarbonImmutable;

final class OrderItem
{
    private function __construct(
        private readonly string $lineId,
        private readonly string $storeId,
        private readonly string $productId,
        private readonly string $productName,
        private readonly int $quantity,
        private readonly RentalPeriod $rentalPeriod,
        private readonly Money $pricePerHour,
        private readonly Money $depositPerUnit,
        private readonly int $billedHours,
        private readonly Money $lineTotal,
        private readonly Money $depositTotal,
        private ItemStatus $status,
        private ?CarbonImmutable $issuedAt,
        private ?string $issuedBy,
        private ?CarbonImmutable $returnedAt,
        private ?string $returnedBy,
    ) {
        if ($quantity < 1 || $quantity > 1000) {
            throw new InvalidQuantity($quantity);
        }
    }

    public static function create(
        string $lineId,
        string $storeId,
        string $productId,
        string $productName,
        int $quantity,
        RentalPeriod $rentalPeriod,
        Money $pricePerHour,
        Money $depositPerUnit,
    ): self {
        $billedHours = $rentalPeriod->billedHours();
        $lineTotal = $pricePerHour->multiply($billedHours)->multiply($quantity);
        $depositTotal = $depositPerUnit->multiply($quantity);

        return new self(
            lineId: $lineId,
            storeId: $storeId,
            productId: $productId,
            productName: $productName,
            quantity: $quantity,
            rentalPeriod: $rentalPeriod,
            pricePerHour: $pricePerHour,
            depositPerUnit: $depositPerUnit,
            billedHours: $billedHours,
            lineTotal: $lineTotal,
            depositTotal: $depositTotal,
            status: ItemStatus::RESERVED,
            issuedAt: null,
            issuedBy: null,
            returnedAt: null,
            returnedBy: null,
        );
    }

    public static function restore(
        string $lineId,
        string $storeId,
        string $productId,
        string $productName,
        int $quantity,
        RentalPeriod $rentalPeriod,
        Money $pricePerHour,
        Money $depositPerUnit,
        int $billedHours,
        Money $lineTotal,
        Money $depositTotal,
        ItemStatus $status,
        ?CarbonImmutable $issuedAt = null,
        ?string $issuedBy = null,
        ?CarbonImmutable $returnedAt = null,
        ?string $returnedBy = null,
    ): self {
        return new self(
            lineId: $lineId,
            storeId: $storeId,
            productId: $productId,
            productName: $productName,
            quantity: $quantity,
            rentalPeriod: $rentalPeriod,
            pricePerHour: $pricePerHour,
            depositPerUnit: $depositPerUnit,
            billedHours: $billedHours,
            lineTotal: $lineTotal,
            depositTotal: $depositTotal,
            status: $status,
            issuedAt: $issuedAt,
            issuedBy: $issuedBy,
            returnedAt: $returnedAt,
            returnedBy: $returnedBy,
        );
    }

    public function lineId(): string
    {
        return $this->lineId;
    }

    public function storeId(): string
    {
        return $this->storeId;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function rentalPeriod(): RentalPeriod
    {
        return $this->rentalPeriod;
    }

    public function pricePerHour(): Money
    {
        return $this->pricePerHour;
    }

    public function depositPerUnit(): Money
    {
        return $this->depositPerUnit;
    }

    public function billedHours(): int
    {
        return $this->billedHours;
    }

    public function lineTotal(): Money
    {
        return $this->lineTotal;
    }

    public function depositTotal(): Money
    {
        return $this->depositTotal;
    }

    public function status(): ItemStatus
    {
        return $this->status;
    }

    public function issuedAt(): ?CarbonImmutable
    {
        return $this->issuedAt;
    }

    public function issuedBy(): ?string
    {
        return $this->issuedBy;
    }

    public function returnedAt(): ?CarbonImmutable
    {
        return $this->returnedAt;
    }

    public function returnedBy(): ?string
    {
        return $this->returnedBy;
    }

    public function isReserved(): bool
    {
        return $this->status === ItemStatus::RESERVED;
    }

    public function isIssued(): bool
    {
        return $this->status === ItemStatus::ISSUED;
    }

    public function isReturned(): bool
    {
        return $this->status === ItemStatus::RETURNED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ItemStatus::CANCELLED;
    }

    public function issue(string $actorId, CarbonImmutable $at): bool
    {
        if ($this->status === ItemStatus::ISSUED) {
            return false;
        }

        if ($this->status !== ItemStatus::RESERVED) {
            throw new InvalidItemTransition($this->lineId, $this->status, ItemStatus::ISSUED);
        }

        $this->status = ItemStatus::ISSUED;
        $this->issuedAt = $at;
        $this->issuedBy = $actorId;

        return true;
    }

    public function return(string $actorId, CarbonImmutable $at): bool
    {
        if ($this->status === ItemStatus::RETURNED) {
            return false;
        }

        if ($this->status !== ItemStatus::ISSUED) {
            throw new InvalidItemTransition($this->lineId, $this->status, ItemStatus::RETURNED);
        }

        $this->status = ItemStatus::RETURNED;
        $this->returnedAt = $at;
        $this->returnedBy = $actorId;

        return true;
    }

    public function cancel(): void
    {
        if ($this->status === ItemStatus::CANCELLED) {
            return;
        }

        if ($this->status === ItemStatus::ISSUED || $this->status === ItemStatus::RETURNED) {
            throw new InvalidItemTransition($this->lineId, $this->status, ItemStatus::CANCELLED);
        }

        $this->status = ItemStatus::CANCELLED;
    }
}
