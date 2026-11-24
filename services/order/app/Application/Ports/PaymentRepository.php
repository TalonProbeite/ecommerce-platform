<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Payments\PaymentRecord;
use App\Application\Payments\RefundRecord;
use Carbon\CarbonImmutable;

interface PaymentRepository
{
    public function findOpenByOrderId(string $orderId): ?PaymentRecord;

    public function countByOrderId(string $orderId): int;

    public function findByProviderPaymentIdForUpdate(string $providerPaymentId): ?PaymentRecord;

    public function findLatestSucceededByOrderId(string $orderId): ?PaymentRecord;

    public function succeededTotalByOrderId(string $orderId): int;

    public function refundedTotalByOrderId(string $orderId): int;

    /**
     * @param list<string> $statuses
     */
    public function hasRefundWithReason(string $orderId, string $reason, array $statuses): bool;

    public function insert(PaymentRecord $payment): void;

    public function updateStatus(
        string $paymentId,
        string $status,
        CarbonImmutable $at,
        ?CarbonImmutable $succeededAt = null,
        ?string $failureCode = null,
    ): void;

    public function insertRefund(RefundRecord $refund): void;

    public function updateRefund(
        string $refundId,
        string $status,
        ?string $providerRefundId,
        CarbonImmutable $at,
    ): void;
}
