<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Money\Money;
use App\Domain\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id(),
            'customer_id' => $this->customerId(),
            'store_id' => $this->storeId(),
            'status' => $this->status()->value,
            'cancel_reason' => $this->cancelReason()?->value,
            'currency' => Money::CURRENCY,
            'subtotal_amount' => $this->subtotal()->amount(),
            'deposit_amount' => $this->deposit()->amount(),
            'total_amount' => $this->total()->amount(),
            'version' => $this->version(),
            'items' => OrderItemResource::collection($this->items()),
            'created_at' => $this->createdAt()->utc()->format(self::DATE_FORMAT),
            'updated_at' => $this->updatedAt()->utc()->format(self::DATE_FORMAT),
            'cancelled_at' => $this->cancelledAt()?->utc()->format(self::DATE_FORMAT),
            'completed_at' => $this->completedAt()?->utc()->format(self::DATE_FORMAT),
        ];
    }
}
