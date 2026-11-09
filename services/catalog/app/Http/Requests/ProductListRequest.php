<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ProductFilters;
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

    public function toDTO(bool $publicOnly = true): ProductFilters
    {
        $validated = $this->validated();

        $availableOnly = array_key_exists('available_only', $validated) && $validated['available_only'] !== null
            ? (bool) filter_var($validated['available_only'], FILTER_VALIDATE_BOOLEAN)
            : false;

        if (array_key_exists('is_active', $validated)) {
            $isActive = $validated['is_active'] !== null
                ? (bool) filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
                : null;
        } else {
            $isActive = $publicOnly ? true : null;
        }

        $sortBy = ProductSortField::CREATED_AT;
        if (isset($validated['sort']) && is_string($validated['sort'])) {
            $sortBy = ProductSortField::tryFrom($validated['sort']) ?? ProductSortField::CREATED_AT;
        }

        $sortDirection = SortDirection::DESC;
        if (isset($validated['sort_direction']) && is_string($validated['sort_direction'])) {
            $sortDirection = SortDirection::tryFrom($validated['sort_direction']) ?? SortDirection::DESC;
        }

        return new ProductFilters(
            storeId: isset($validated['store_id']) ? (string) $validated['store_id'] : null,
            categoryId: isset($validated['category_id']) ? (string) $validated['category_id'] : null,
            minPrice: isset($validated['min_price']) ? (int) $validated['min_price'] : null,
            maxPrice: isset($validated['max_price']) ? (int) $validated['max_price'] : null,
            maxDeposit: isset($validated['max_deposit']) ? (int) $validated['max_deposit'] : null,
            isActive: $isActive,
            isAvailable: $availableOnly ? true : null,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            limit: isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
            page: isset($validated['page']) ? (int) $validated['page'] : 1,
            publicOnly: $publicOnly,
        );
    }
}
