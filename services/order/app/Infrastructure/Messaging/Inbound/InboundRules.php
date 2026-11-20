<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Inbound;

/**
 * Shared validation rules for inbound payloads consumed by Order.
 *
 * Timestamps must carry an explicit numeric offset (for example +00:00) and may
 * include optional microseconds. The "Z" suffix and offset-less values are
 * rejected by the contract.
 */
final class InboundRules
{
    public const EVENT_ID = ['required', 'uuid'];
    public const SCHEMA_VERSION = ['required', 'integer', 'in:1'];
    public const TIMESTAMP = ['required', 'date_format:Y-m-d\TH:i:s.uP,Y-m-d\TH:i:sP'];
    public const UUID = ['required', 'uuid'];

    public const ORDER_ID = ['required', 'uuid'];
    public const STORE_ID = ['required', 'uuid'];
    public const PRODUCT_ID = ['required', 'uuid'];
    public const CATEGORY_ID = ['required', 'uuid'];

    public const RESERVATION_FAILURE_REASON = ['required', 'string', 'in:insufficient_stock,product_not_found'];

    public const PRODUCT_NAME = ['required', 'string', 'max:255'];
    public const PRICE_PER_HOUR = ['required', 'integer', 'min:0'];
    public const DEPOSIT_AMOUNT = ['required', 'integer', 'min:0'];
    public const IS_ACTIVE = ['required', 'boolean'];

    private function __construct()
    {
    }
}
