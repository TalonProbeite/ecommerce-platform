<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Application\Ports\PaymentRepository;
use App\Infrastructure\Persistence\Models\PaymentModel;
use App\Infrastructure\Persistence\Models\RefundModel;
use Carbon\CarbonImmutable;

final readonly class EloquentPaymentRepository implements PaymentRepository
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function findOpenByOrderId(string $orderId): ?PaymentRecord
    {
        $model = PaymentModel::query()
            ->where('order_id', $orderId)
            ->whereIn('status', PaymentStatus::open())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $model !== null ? $this->toRecord($model) : null;
    }

    public function countByOrderId(string $orderId): int
    {
        return PaymentModel::query()->where('order_id', $orderId)->count();
    }

    public function findByProviderPaymentIdForUpdate(string $providerPaymentId): ?PaymentRecord
    {
        $model = PaymentModel::query()
            ->where('provider_payment_id', $providerPaymentId)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toRecord($model) : null;
    }

    public function findLatestSucceededByOrderId(string $orderId): ?PaymentRecord
    {
        $model = PaymentModel::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::SUCCEEDED)
            ->orderByDesc('succeeded_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $model !== null ? $this->toRecord($model) : null;
    }

    public function succeededTotalByOrderId(string $orderId): int
    {
        return (int) PaymentModel::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::SUCCEEDED)
            ->sum('amount');
    }

    public function refundedTotalByOrderId(string $orderId): int
    {
        return (int) RefundModel::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [RefundStatus::PENDING, RefundStatus::SUCCEEDED])
            ->sum('amount');
    }

    public function hasRefundWithReason(string $orderId, string $reason, array $statuses): bool
    {
        return RefundModel::query()
            ->where('order_id', $orderId)
            ->where('reason', $reason)
            ->whereIn('status', $statuses)
            ->exists();
    }

    public function insert(PaymentRecord $payment): void
    {
        PaymentModel::query()->create([
            'id' => $payment->id,
            'order_id' => $payment->orderId,
            'provider' => $payment->provider,
            'provider_payment_id' => $payment->providerPaymentId,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'failure_code' => $payment->failureCode,
            'succeeded_at' => $payment->succeededAt?->format(self::TIMESTAMP_FORMAT),
            'created_at' => $payment->createdAt->format(self::TIMESTAMP_FORMAT),
            'updated_at' => $payment->updatedAt->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function updateStatus(
        string $paymentId,
        string $status,
        CarbonImmutable $at,
        ?CarbonImmutable $succeededAt = null,
        ?string $failureCode = null,
    ): void {
        PaymentModel::query()->whereKey($paymentId)->update([
            'status' => $status,
            'failure_code' => $failureCode,
            'succeeded_at' => $succeededAt?->format(self::TIMESTAMP_FORMAT),
            'updated_at' => $at->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function insertRefund(RefundRecord $refund): void
    {
        RefundModel::query()->create([
            'id' => $refund->id,
            'order_id' => $refund->orderId,
            'order_item_id' => $refund->orderItemId,
            'payment_id' => $refund->paymentId,
            'provider_refund_id' => $refund->providerRefundId,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'created_at' => $refund->createdAt->format(self::TIMESTAMP_FORMAT),
            'updated_at' => $refund->updatedAt->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function updateRefund(
        string $refundId,
        string $status,
        ?string $providerRefundId,
        CarbonImmutable $at,
    ): void {
        RefundModel::query()->whereKey($refundId)->update([
            'status' => $status,
            'provider_refund_id' => $providerRefundId,
            'updated_at' => $at->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    private function toRecord(PaymentModel $model): PaymentRecord
    {
        return new PaymentRecord(
            id: (string) $model->id,
            orderId: (string) $model->order_id,
            provider: (string) $model->provider,
            providerPaymentId: (string) $model->provider_payment_id,
            amount: (int) $model->amount,
            currency: (string) $model->currency,
            status: (string) $model->status,
            failureCode: $model->failure_code !== null ? (string) $model->failure_code : null,
            succeededAt: $model->succeeded_at !== null ? $this->immutable($model->succeeded_at) : null,
            createdAt: $this->immutable($model->created_at),
            updatedAt: $this->immutable($model->updated_at),
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
}
