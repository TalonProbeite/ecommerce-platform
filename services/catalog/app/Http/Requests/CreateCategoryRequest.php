<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\CreateCategoryData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
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
            'slug' => 'required|alpha_dash|max:255',
            'parent_id' => 'nullable|uuid',
        ];
    }

    public function toDTO(): CreateCategoryData
    {
        $validated = $this->validated();

        return new CreateCategoryData(
            name: (string) $validated['name'],
            slug: (string) $validated['slug'],
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
        );
    }
}
