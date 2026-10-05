<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class ProductAttribute extends Model
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
            'name'  => 'string',
            'value' => 'string',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}