<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

/**
 * Shared validation rules for inbound payloads. Keep limits here so that
 * the contract is defined in exactly one place.
 *
 * Timestamps must carry an explicit numeric offset (for example +00:00).
 * The "Z" suffix and offset-less values are rejected by the contract.
 */
final class InboundRules
{
    public const MAX_ITEMS = 100;
    public const MAX_QUANTITY = 1000;

    public const EVENT_ID = ['required', 'uuid'];
    public const UUID = ['required', 'uuid'];
    public const TIMESTAMP = ['required', 'date_format:Y-m-d\TH:i:sP'];
    public const QUANTITY = ['required', 'integer', 'min:1', 'max:' . self::MAX_QUANTITY];

    /**
     * @return list<string>
     */
    public static function items(): array
    {
        return ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS];
    }

    private function __construct()
    {
    }
}
