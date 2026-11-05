<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\StoreFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function toDTO(): StoreFilters
    {
        $validated = $this->validated();

        $active = true;
        if (array_key_exists('active', $validated)) {
            $active = $validated['active'] !== null
                ? (bool) filter_var($validated['active'], FILTER_VALIDATE_BOOLEAN)
                : null;
        }

        return new StoreFilters(
            active: $active,
            limit: isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
            page: isset($validated['page']) ? (int) $validated['page'] : 1,
        );
    }
}
