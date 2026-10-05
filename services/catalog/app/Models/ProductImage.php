<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class ProductImage extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'path'       => 'string',
            'is_main'    => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}