<?php

declare(strict_types=1);

namespace App\Application\Query\OverdueRentals;

use Carbon\CarbonImmutable;
use JsonException;

final readonly class OverdueRentalCursor
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s.uP';

    public function __construct(
        public CarbonImmutable $rentalEndAt,
        public string $id,
    ) {
    }

    public static function decode(string $value): ?self
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode(strtr($value, '-_', '+/'), true);

        if ($json === false || $json === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $rentalEndAt = $data['rental_end_at'] ?? null;
        $id = $data['id'] ?? null;

        if (! is_string($rentalEndAt) || ! is_string($id) || $id === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat(self::DATE_FORMAT, $rentalEndAt);
        } catch (\Throwable) {
            return null;
        }

        if (! $parsed instanceof CarbonImmutable) {
            return null;
        }

        return new self($parsed, $id);
    }

    public function encode(): string
    {
        $json = json_encode([
            'rental_end_at' => $this->rentalEndAt->format(self::DATE_FORMAT),
            'id' => $this->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
