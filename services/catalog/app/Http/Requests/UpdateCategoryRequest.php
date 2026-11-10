<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\UpdateCategoryData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
            'slug' => [
                'sometimes',
                'alpha_dash',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where('store_id', $this->route('storeId'))
                    ->ignore($this->route('categoryId')),
            ],
            'parent_id' => 'sometimes|nullable|uuid',
        ];
    }

    public function toDTO(): UpdateCategoryData
    {
        $validated = $this->validated();

        return new UpdateCategoryData(
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            slug: isset($validated['slug']) ? (string) $validated['slug'] : null,
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
            nameProvided: $this->has('name'),
            slugProvided: $this->has('slug'),
            parentIdProvided: $this->has('parent_id'),
        );
    }
}
