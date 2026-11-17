<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundRecord;
use App\Application\Payments\RefundStatus;
use App\Application\Ports\PaymentRepository;
use Carbon\CarbonImmutable;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /**
     * @var array<string, PaymentRecord>
     */
    private array $payments = [];

    /**
     * @var array<string, RefundRecord>
     */
    private array $refunds = [];

    public function findOpenByOrderId(string $orderId): ?PaymentRecord
    {
        foreach (array_reverse($this->payments, true) as $payment) {
            if ($payment->orderId === $orderId && in_array($payment->status, PaymentStatus::open(), true)) {
                return $payment;
            }
        }

        return null;
    }

    public function countByOrderId(string $orderId): int
    {
        return count(array_filter(
            $this->payments,
            static fn (PaymentRecord $payment): bool => $payment->orderId === $orderId,
        ));
    }

    public function findByProviderPaymentIdForUpdate(string $providerPaymentId): ?PaymentRecord
    {
        foreach ($this->payments as $payment) {
            if ($payment->providerPaymentId === $providerPaymentId) {
                return $payment;
            }
        }

        return null;
    }

    public function findLatestSucceededByOrderId(string $orderId): ?PaymentRecord
    {
        foreach (array_reverse($this->payments, true) as $payment) {
            if ($payment->orderId === $orderId && $payment->status === PaymentStatus::SUCCEEDED) {
                return $payment;
            }
        }

        return null;
    }

    public function succeededTotalByOrderId(string $orderId): int
    {
        $total = 0;

        foreach ($this->payments as $payment) {
            if ($payment->orderId === $orderId && $payment->status === PaymentStatus::SUCCEEDED) {
                $total += $payment->amount;
            }
        }

        return $total;
    }

    public function refundedTotalByOrderId(string $orderId): int
    {
        $total = 0;

        foreach ($this->refunds as $refund) {
            if (
                $refund->orderId === $orderId
                && in_array($refund->status, [RefundStatus::PENDING, RefundStatus::SUCCEEDED], true)
            ) {
                $total += $refund->amount;
            }
        }

        return $total;
    }

    public function hasRefundWithReason(string $orderId, string $reason, array $statuses): bool
    {
        foreach ($this->refunds as $refund) {
            if (
                $refund->orderId === $orderId
                && $refund->reason === $reason
                && in_array($refund->status, $statuses, true)
            ) {
                return true;
            }
        }

        return false;
    }

    public function insert(PaymentRecord $payment): void
    {
        $this->payments[$payment->id] = $payment;
    }

    public function updateStatus(
        string $paymentId,
        string $status,
        CarbonImmutable $at,
        ?CarbonImmutable $succeededAt = null,
        ?string $failureCode = null,
    ): void {
        $payment = $this->payments[$paymentId] ?? null;

        if ($payment === null) {
            return;
        }

        $this->payments[$paymentId] = new PaymentRecord(
            id: $payment->id,
            orderId: $payment->orderId,
            provider: $payment->provider,
            providerPaymentId: $payment->providerPaymentId,
            amount: $payment->amount,
            currency: $payment->currency,
            status: $status,
            failureCode: $failureCode,
            succeededAt: $succeededAt,
            createdAt: $payment->createdAt,
            updatedAt: $at,
        );
    }

    public function insertRefund(RefundRecord $refund): void
    {
        $this->refunds[$refund->id] = $refund;
    }

    public function updateRefund(
        string $refundId,
        string $status,
        ?string $providerRefundId,
        CarbonImmutable $at,
    ): void {
        $refund = $this->refunds[$refundId] ?? null;

        if ($refund === null) {
            return;
        }

        $this->refunds[$refundId] = new RefundRecord(
            id: $refund->id,
            orderId: $refund->orderId,
            orderItemId: $refund->orderItemId,
            paymentId: $refund->paymentId,
            providerRefundId: $providerRefundId,
            amount: $refund->amount,
            reason: $refund->reason,
            status: $status,
            createdAt: $refund->createdAt,
            updatedAt: $at,
        );
    }

    /**
     * @return list<PaymentRecord>
     */
    public function payments(): array
    {
        return array_values($this->payments);
    }

    /**
     * @return list<RefundRecord>
     */
    public function refunds(): array
    {
        return array_values($this->refunds);
    }
}
