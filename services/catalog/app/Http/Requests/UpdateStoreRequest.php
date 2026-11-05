<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\UpdateStoreData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'location' => 'sometimes|nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ];
    }

    public function toDTO(): UpdateStoreData
    {
        $validated = $this->validated();

        return new UpdateStoreData(
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            location: isset($validated['location']) ? (string) $validated['location'] : null,
            active: array_key_exists('active', $validated)
                ? (bool) filter_var($validated['active'], FILTER_VALIDATE_BOOLEAN)
                : null,
            nameProvided: $this->has('name'),
            descriptionProvided: $this->has('description'),
            locationProvided: $this->has('location'),
            activeProvided: $this->has('active'),
        );
    }
}
