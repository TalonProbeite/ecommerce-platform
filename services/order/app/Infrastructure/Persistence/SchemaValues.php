<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Payments\PaymentStatus;
use App\Application\Payments\RefundStatus;

final class SchemaValues
{
    public const ORDER_STATUS = [
        'created',
        'stock_reserved',
        'payment_pending',
        'paid',
        'in_rental',
        'completed',
        'cancelled',
    ];

    public const CANCEL_REASON = [
        'stock_unavailable',
        'payment_timeout',
        'customer_request',
        'admin_request',
    ];

    public const ITEM_STATUS = [
        'reserved',
        'issued',
        'returned',
        'cancelled',
    ];

    /**
     * Сверено со stripe/stripe-php 23.0.0 (API 2026-10-28.endive).
     */
    public const array PAYMENT_STATUS = PaymentStatus::ALL;

    /**
     * Сверено со stripe/stripe-php 23.0.0 (API 2026-10-28.endive).
     * requires_action из Stripe приводится к pending.
     */
    public const array REFUND_STATUS = RefundStatus::ALL;

    public const REFUND_REASON = [
        'order_cancelled',
        'item_returned',
    ];

    public const ACTOR_ROLE = [
        'customer',
        'store_manager',
        'admin',
        'analyst',
        'system',
        'stripe',
    ];

    /**
     * @param list<string> $values
     */
    public static function quotedList(array $values): string
    {
        return implode("', '", $values);
    }
}
