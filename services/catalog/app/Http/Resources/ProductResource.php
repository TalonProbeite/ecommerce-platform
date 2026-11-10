<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 * @property ?string $category_name
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_per_hour' => $this->price_per_hour,
            'deposit_amount' => $this->deposit_amount,
            'is_active' => $this->is_active,
            'main_image_url' => $this->whenLoaded(
                'mainImage',
                fn (?ProductImage $mainImage) => $mainImage?->url(),
                null,
            ),
            'category_name' => $this->category_name ?? $this->whenLoaded(
                'category',
                fn (?Category $category) => $category?->name,
                null,
            ),
            'category' => $this->whenLoaded(
                'category',
                fn (?Category $category) => $category === null ? null : [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
            ),
            'attributes' => $this->whenLoaded(
                'attributes',
                fn ($attributes) => ProductAttributeResource::collection($attributes),
            ),
            'images' => $this->whenLoaded(
                'images',
                fn ($images) => ProductImageResource::collection($images),
            ),
            'stock' => $this->whenLoaded(
                'stock',
                fn (?Stock $stock) => $stock === null ? null : new StockResource($stock),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
