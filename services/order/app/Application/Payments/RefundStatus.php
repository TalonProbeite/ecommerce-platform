<?php

declare(strict_types=1);

namespace App\Application\Payments;

final class RefundStatus
{
    /*
     * Сверено со stripe/stripe-php 23.0.0 (API 2026-10-28.endive).
     * Stripe\Refund::STATUS_*: canceled, failed, pending, requires_action, succeeded.
     * requires_action из Stripe приводится к pending (StripePaymentGateway::mapRefundStatus()).
     */
    public const string PENDING = 'pending';
    public const string SUCCEEDED = 'succeeded';
    public const string FAILED = 'failed';
    public const string CANCELED = 'canceled';

    /**
     * @var list<string>
     */
    public const array ALL = [
        self::PENDING,
        self::SUCCEEDED,
        self::FAILED,
        self::CANCELED,
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function isAllowed(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    private function __construct()
    {
    }
}
