<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProductSortField;
use App\Enums\SortDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductListRequest extends FormRequest
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
            'store_id' => 'nullable|uuid',
            'category_id' => 'nullable|uuid',
            'min_price' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'max_deposit' => 'nullable|integer|min:0',
            'available_only' => 'nullable|in:true,false,1,0',
            'is_active' => 'nullable|boolean',
            'sort' => ['nullable', Rule::enum(ProductSortField::class)],
            'sort_direction' => ['nullable', Rule::enum(SortDirection::class)],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
