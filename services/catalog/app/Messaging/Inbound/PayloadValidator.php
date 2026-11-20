<?php

declare(strict_types=1);

namespace App\Messaging\Inbound;

use App\Messaging\Inbound\Exceptions\InvalidMessageException;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

/**
 * Single place that turns Laravel validation failures into InvalidMessageException.
 */
final class PayloadValidator
{
    /**
     * @param array<array-key, mixed> $payload
     * @param array<string, mixed> $rules
     * @param (Closure(LaravelValidator): void)|null $after Extra checks run after the rules.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidMessageException
     */
    public static function validate(array $payload, array $rules, ?Closure $after = null): array
    {
        $validator = Validator::make($payload, $rules);

        if ($after !== null) {
            $validator->after($after);
        }

        if ($validator->fails()) {
            $errors = array_map(array_values(...), $validator->errors()->toArray());

            throw new InvalidMessageException($errors);
        }

        return $validator->validated();
    }

    private function __construct()
    {
    }
}
