<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
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
            'product_id' => $this->product_id,
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_main' => $this->is_main,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
