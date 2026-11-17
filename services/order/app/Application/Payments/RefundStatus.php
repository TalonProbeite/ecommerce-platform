<?php

declare(strict_types=1);

namespace App\Application\Payments;

final class RefundStatus
{
    public const string PENDING = 'pending';
    public const string SUCCEEDED = 'succeeded';
    public const string FAILED = 'failed';
    public const string CANCELED = 'canceled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELED,
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
