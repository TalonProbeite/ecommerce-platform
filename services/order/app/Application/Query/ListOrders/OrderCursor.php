<?php

declare(strict_types=1);

namespace App\Application\Query\ListOrders;

use App\Domain\Order\Order;
use Carbon\CarbonImmutable;
use JsonException;

final readonly class OrderCursor
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        public CarbonImmutable $createdAt,
        public string $id,
    ) {
    }

    public static function forOrder(Order $order): self
    {
        return new self($order->createdAt(), $order->id());
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

        $createdAt = $data['created_at'] ?? null;
        $id = $data['id'] ?? null;

        if (! is_string($createdAt) || ! is_string($id) || $id === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::parse($createdAt);
        } catch (\Throwable) {
            return null;
        }

        return new self($parsed, $id);
    }

    public function encode(): string
    {
        $json = json_encode([
            'created_at' => $this->createdAt->format(self::DATE_FORMAT),
            'id' => $this->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
