<?php

declare(strict_types=1);

namespace App\Application\Payments;

final class PaymentStatus
{
    public const string REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    public const string REQUIRES_CONFIRMATION = 'requires_confirmation';
    public const string REQUIRES_ACTION = 'requires_action';
    public const string PROCESSING = 'processing';
    public const string REQUIRES_CAPTURE = 'requires_capture';
    public const string SUCCEEDED = 'succeeded';
    public const string CANCELED = 'canceled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REQUIRES_PAYMENT_METHOD,
            self::REQUIRES_CONFIRMATION,
            self::REQUIRES_ACTION,
            self::PROCESSING,
            self::REQUIRES_CAPTURE,
            self::SUCCEEDED,
            self::CANCELED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function open(): array
    {
        return [
            self::REQUIRES_PAYMENT_METHOD,
            self::REQUIRES_ACTION,
            self::PROCESSING,
            self::REQUIRES_CONFIRMATION,
        ];
    }

    public static function isAllowed(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    private function __construct()
    {
    }
}
