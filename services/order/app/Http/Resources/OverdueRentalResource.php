<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Query\OverdueRentals\OverdueRentalItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OverdueRentalItem
 */
final class OverdueRentalResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->orderId,
            'item_id' => $this->itemId,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'store_id' => $this->storeId,
            'customer_id' => $this->customerId,
            'quantity' => $this->quantity,
            'rental_end_at' => $this->rentalEndAt->utc()->format(self::DATE_FORMAT),
            'overdue_hours' => $this->overdueHours,
        ];
    }
}
