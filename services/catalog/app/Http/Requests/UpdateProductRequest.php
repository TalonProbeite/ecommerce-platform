<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
                Rule::unique('products', 'slug')
                    ->where('store_id', $this->route('storeId'))
                    ->ignore($this->route('productId')),
            ],
            'category_id' => 'sometimes|uuid',
            'price_per_hour' => 'sometimes|integer|min:0',
            'deposit_amount' => 'sometimes|integer|min:0',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            'attributes' => 'sometimes|array',
            'attributes.*.name' => 'required|string|max:255',
            'attributes.*.value' => 'required|string|max:255',
        ];
    }
}
