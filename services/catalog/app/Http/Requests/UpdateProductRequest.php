<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ProductAttributeData;
use App\DTO\UpdateProductData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'slug' => 'sometimes|alpha_dash|max:255',
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

    public function toDTO(): UpdateProductData
    {
        $validated = $this->validated();

        $attributes = null;

        if ($this->has('attributes')) {
            /** @var array<int, array{name: string, value: string}> $rows */
            $rows = $validated['attributes'] ?? [];
            $attributes = $this->mapAttributes($rows);
        }

        return new UpdateProductData(
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            slug: isset($validated['slug']) ? (string) $validated['slug'] : null,
            categoryId: isset($validated['category_id']) ? (string) $validated['category_id'] : null,
            pricePerHour: isset($validated['price_per_hour']) ? (int) $validated['price_per_hour'] : null,
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            depositAmount: isset($validated['deposit_amount']) ? (int) $validated['deposit_amount'] : null,
            isActive: array_key_exists('is_active', $validated)
                ? (bool) filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
                : null,
            nameProvided: $this->has('name'),
            slugProvided: $this->has('slug'),
            categoryIdProvided: $this->has('category_id'),
            pricePerHourProvided: $this->has('price_per_hour'),
            descriptionProvided: $this->has('description'),
            depositAmountProvided: $this->has('deposit_amount'),
            isActiveProvided: $this->has('is_active'),
            attributes: $attributes,
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
