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
            'active' => 'nullable|in:true,false,1,0',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function toDTO(): StoreFilters
    {
        $validated = $this->validated();

        return new StoreFilters(
            active: $this->resolveActive($validated),
            limit: (int) ($validated['per_page'] ?? 20),
            page: (int) ($validated['page'] ?? 1),
        );
    }

    /**
     * Absent: default filter (true). Explicit null: no filter (null).
     *
     * @param array<string, mixed> $validated
     */
    private function resolveActive(array $validated): ?bool
    {
        if (! array_key_exists('active', $validated)) {
            return true;
        }

        if ($validated['active'] === null) {
            return null;
        }

        return filter_var($validated['active'], FILTER_VALIDATE_BOOLEAN);
    }
}