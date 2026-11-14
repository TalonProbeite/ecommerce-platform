<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

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
     * Stripe PaymentIntent statuses as published on 2026-11-14.
     */
    public const PAYMENT_STATUS = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'processing',
        'requires_capture',
        'succeeded',
        'canceled',
    ];

    public const REFUND_STATUS = [
        'pending',
        'succeeded',
        'failed',
    ];

    public const REFUND_REASON = [
        'order_cancelled',
        'item_returned',
    ];

    public const ACTOR_ROLE = [
        'customer',
        'manager',
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
