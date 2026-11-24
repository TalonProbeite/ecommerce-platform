<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Order\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->lineId(),
            'product_id' => $this->productId(),
            'product_name' => $this->productName(),
            'quantity' => $this->quantity(),
            'rental_start_at' => $this->rentalPeriod()->start()->utc()->format(self::DATE_FORMAT),
            'rental_end_at' => $this->rentalPeriod()->end()->utc()->format(self::DATE_FORMAT),
            'billed_hours' => $this->billedHours(),
            'price_per_hour' => $this->pricePerHour()->amount(),
            'deposit_per_unit' => $this->depositPerUnit()->amount(),
            'line_total' => $this->lineTotal()->amount(),
            'deposit_total' => $this->depositTotal()->amount(),
            'status' => $this->status()->value,
            'issued_at' => $this->issuedAt()?->utc()->format(self::DATE_FORMAT),
            'issued_by' => $this->issuedBy(),
            'returned_at' => $this->returnedAt()?->utc()->format(self::DATE_FORMAT),
            'returned_by' => $this->returnedBy(),
        ];
    }
}
