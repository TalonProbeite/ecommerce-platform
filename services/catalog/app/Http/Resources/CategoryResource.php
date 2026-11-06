<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => $this->whenLoaded(
                'recursiveChildren',
                fn ($children) => self::collection($children),
            ),
            'ancestors' => $this->whenLoaded(
                'parent',
                fn (?Category $parent) => $this->resolveAncestors($parent),
                $this->whenLoaded(
                    'recursiveParent',
                    fn (?Category $parent) => $this->resolveAncestors($parent),
                ),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build the ancestors chain from root down to immediate parent without triggering DB queries.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function resolveAncestors(?Category $parent): array
    {
        $ancestors = [];
        $current = $parent;

        while ($current !== null) {
            $ancestors[] = [
                'id' => $current->id,
                'name' => $current->name,
            ];

            if ($current->relationLoaded('parent')) {
                $current = $current->getRelation('parent');
            } elseif ($current->relationLoaded('recursiveParent')) {
                $current = $current->getRelation('recursiveParent');
            } else {
                break;
            }
        }

        return array_reverse($ancestors);
    }
}
