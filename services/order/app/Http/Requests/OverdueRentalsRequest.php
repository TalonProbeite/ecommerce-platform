<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

final class OverdueRentalsRequest extends ApiFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['nullable', 'uuid'],
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
