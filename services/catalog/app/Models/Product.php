<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Product extends Model
{
    use HasUuids;
    use SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $fillable = [
        'name', 'slug', 'description', 'price_per_hour', 'deposit_amount',
        'rating', 'reviews_count', 'is_active', 'store_id', 'category_id',
    ];


    protected function casts(): array
    {
        return [
            'name' => 'string',
            'is_active' => 'boolean',
            'slug' => 'string',
            'description' => 'string',
            'price_per_hour' => 'integer',
            'deposit_amount' => 'integer',
            'rating' => 'decimal:2',
            'reviews_count' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
