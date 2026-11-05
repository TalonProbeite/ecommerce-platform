<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\DTO\CreateStoreData;
use Illuminate\Contracts\Validation\ValidationRule;

class CreateStoreRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ];
    }

    public function toDTO(): CreateStoreData
    {
        $validate = $this->validated();

        return new CreateStoreData(
            name: $validate['name'],
            description: $validate['description'] ?? null,
            location: $validate['location'] ?? null,
            active: $validate['active'] ?? true,
        );
    }
}
