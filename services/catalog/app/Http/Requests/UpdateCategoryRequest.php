<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
}
