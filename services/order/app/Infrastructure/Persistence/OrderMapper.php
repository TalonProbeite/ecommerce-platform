<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Clock;
use App\Domain\Events\OrderStatusChanged;
use App\Domain\Money\Money;
use App\Domain\Order\CancelReason;
use App\Domain\Order\ItemStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Rental\RentalPeriod;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use Carbon\CarbonImmutable;

final readonly class OrderMapper
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function __construct(private Clock $clock)
    {
    }

    public function toAggregate(OrderModel $model): Order
    {
        $itemModels = $model->items()->orderBy('created_at')->orderBy('id')->get();
        $storeId = (string) $model->store_id;

        $items = [];

        foreach ($itemModels as $itemModel) {
            $items[] = $this->toItem($itemModel, $storeId);
        }

        return Order::restore(
            id: (string) $model->id,
            customerId: (string) $model->customer_id,
            storeId: $storeId,
            status: OrderStatus::from((string) $model->status),
            cancelReason: $model->cancel_reason !== null
                ? CancelReason::from((string) $model->cancel_reason)
                : null,
            items: $items,
            subtotal: Money::fromMinorUnits((int) $model->subtotal_amount),
            deposit: Money::fromMinorUnits((int) $model->deposit_amount),
            version: (int) $model->version,
            idempotencyKey: (string) $model->idempotency_key,
            requestFingerprint: (string) $model->request_fingerprint,
            createdAt: $this->immutable($model->created_at),
            updatedAt: $this->immutable($model->updated_at),
            cancelledAt: $model->cancelled_at !== null ? $this->immutable($model->cancelled_at) : null,
            completedAt: $model->completed_at !== null ? $this->immutable($model->completed_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function orderAttributes(Order $order, CarbonImmutable $now): array
    {
        return [
            'id' => $order->id(),
            'customer_id' => $order->customerId(),
            'store_id' => $order->storeId(),
            'status' => $order->status()->value,
            'cancel_reason' => $order->cancelReason()?->value,
            'currency' => Money::CURRENCY,
            'subtotal_amount' => $order->subtotal()->amount(),
            'deposit_amount' => $order->deposit()->amount(),
            'idempotency_key' => $order->idempotencyKey(),
            'request_fingerprint' => $order->requestFingerprint(),
            'version' => $order->version(),
            'cancelled_at' => $this->format($order->cancelledAt()),
            'completed_at' => $this->format($order->completedAt()),
            'created_at' => $this->format($order->createdAt()),
            'updated_at' => $this->format($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAttributes(
        OrderItem $item,
        string $orderId,
        CarbonImmutable $createdAt,
        CarbonImmutable $now,
    ): array {
        return [
            'id' => $item->lineId(),
            'order_id' => $orderId,
            'product_id' => $item->productId(),
            'product_name' => $item->productName(),
            'quantity' => $item->quantity(),
            'rental_start_at' => $this->format($item->rentalPeriod()->start()),
            'rental_end_at' => $this->format($item->rentalPeriod()->end()),
            'billed_hours' => $item->billedHours(),
            'price_per_hour' => $item->pricePerHour()->amount(),
            'deposit_per_unit' => $item->depositPerUnit()->amount(),
            'line_total' => $item->lineTotal()->amount(),
            'deposit_total' => $item->depositTotal()->amount(),
            'item_status' => $item->status()->value,
            'issued_at' => $this->format($item->issuedAt()),
            'issued_by' => $item->issuedBy(),
            'returned_at' => $this->format($item->returnedAt()),
            'returned_by' => $item->returnedBy(),
            'created_at' => $this->format($createdAt),
            'updated_at' => $this->format($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusChangeAttributes(OrderStatusChanged $change): array
    {
        return [
            'order_id' => $change->orderId,
            'order_item_id' => null,
            'from_status' => $change->fromStatus?->value,
            'to_status' => $change->toStatus->value,
            'actor_id' => $change->actorId,
            'actor_role' => $change->actorRole->value,
            'payload' => $change->itemIds !== [] ? ['item_ids' => $change->itemIds] : null,
            'occurred_at' => $this->format($change->at),
        ];
    }

    private function toItem(OrderItemModel $model, string $storeId): OrderItem
    {
        return OrderItem::restore(
            lineId: (string) $model->id,
            storeId: $storeId,
            productId: (string) $model->product_id,
            productName: (string) $model->product_name,
            quantity: (int) $model->quantity,
            rentalPeriod: new RentalPeriod(
                $this->immutable($model->rental_start_at),
                $this->immutable($model->rental_end_at),
            ),
            pricePerHour: Money::fromMinorUnits((int) $model->price_per_hour),
            depositPerUnit: Money::fromMinorUnits((int) $model->deposit_per_unit),
            billedHours: (int) $model->billed_hours,
            lineTotal: Money::fromMinorUnits((int) $model->line_total),
            depositTotal: Money::fromMinorUnits((int) $model->deposit_total),
            status: ItemStatus::from((string) $model->item_status),
            issuedAt: $model->issued_at !== null ? $this->immutable($model->issued_at) : null,
            issuedBy: $model->issued_by !== null ? (string) $model->issued_by : null,
            returnedAt: $model->returned_at !== null ? $this->immutable($model->returned_at) : null,
            returnedBy: $model->returned_by !== null ? (string) $model->returned_by : null,
        );
    }

    private function immutable(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function format(?CarbonImmutable $at): ?string
    {
        return $at?->format(self::TIMESTAMP_FORMAT);
    }
}
