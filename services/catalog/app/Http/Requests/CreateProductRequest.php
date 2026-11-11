<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
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
            'slug' => [
                'required',
                'alpha_dash',
                'max:255',
                Rule::unique('products', 'slug')->where('store_id', $this->route('storeId')),
            ],
            'category_id' => 'required|uuid',
            'price_per_hour' => 'required|integer|min:0',
            'deposit_amount' => 'nullable|integer|min:0',
            'quantity' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:255',
            'attributes.*.value' => 'required|string|max:255',
        ];
    }
}
