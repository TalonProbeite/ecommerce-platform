<?php

declare(strict_types=1);

namespace App\Application\Customer\CreateOrder;

final class RequestFingerprint
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public static function for(CreateOrderCommand $command): string
    {
        $items = [];

        foreach ($command->items as $item) {
            $items[] = [
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
                'rental_end_at' => $item->rentalEndAt->format(self::DATE_FORMAT),
                'rental_start_at' => $item->rentalStartAt->format(self::DATE_FORMAT),
            ];
        }

        return hash('sha256', self::encode(['items' => $items]));
    }

    private static function encode(mixed $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::canonicalize($item),
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    private function __construct()
    {
    }
}
