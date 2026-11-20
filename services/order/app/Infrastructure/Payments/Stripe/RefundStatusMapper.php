<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Application\Payments\RefundStatus;
use Psr\Log\LoggerInterface;
use Stripe\Refund;

final readonly class RefundStatusMapper
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Stripe refund statuses are pending, requires_action, succeeded, failed and canceled.
     * requires_action is stored as pending, an unknown status is treated as failed.
     */
    public function map(?string $status, ?string $refundId): string
    {
        return match ($status) {
            Refund::STATUS_PENDING, Refund::STATUS_REQUIRES_ACTION => RefundStatus::PENDING,
            Refund::STATUS_SUCCEEDED => RefundStatus::SUCCEEDED,
            Refund::STATUS_FAILED => RefundStatus::FAILED,
            Refund::STATUS_CANCELED => RefundStatus::CANCELED,
            default => $this->unknown($status, $refundId),
        };
    }

    private function unknown(?string $status, ?string $refundId): string
    {
        $this->logger->critical('Stripe returned an unknown refund status.', [
            'refund_id' => $refundId,
            'status' => $status,
        ]);

        return RefundStatus::FAILED;
    }
}
