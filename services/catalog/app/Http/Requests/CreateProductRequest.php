<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\CreateProductData;
use App\DTO\ProductAttributeData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'slug' => 'required|alpha_dash|max:255',
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

    public function toDTO(): CreateProductData
    {
        $validated = $this->validated();

        /** @var array<int, array{name: string, value: string}> $attributes */
        $attributes = $validated['attributes'] ?? [];

        return new CreateProductData(
            name: (string) $validated['name'],
            slug: (string) $validated['slug'],
            categoryId: (string) $validated['category_id'],
            pricePerHour: (int) $validated['price_per_hour'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            depositAmount: isset($validated['deposit_amount']) ? (int) $validated['deposit_amount'] : 0,
            isActive: array_key_exists('is_active', $validated) && $validated['is_active'] !== null
                ? (bool) filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
            quantity: (int) $validated['quantity'],
            attributes: $this->mapAttributes($attributes),
        );
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $attributes
     * @return array<int, ProductAttributeData>
     */
    private function mapAttributes(array $attributes): array
    {
        return array_map(
            static fn (array $attribute): ProductAttributeData => new ProductAttributeData(
                name: $attribute['name'],
                value: $attribute['value'],
            ),
            $attributes,
        );
    }
}
