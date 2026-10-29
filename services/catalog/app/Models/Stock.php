<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Ramsey\Uuid\Uuid;

/**
 * @mixin IdeHelperStock
 */
class Stock extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $fillable = ['product_id', 'store_id', 'quantity', 'reserved'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved' => 'integer',
        ];
    }


    public function getAvailableAttribute(): int
    {
        return $this->quantity - $this->reserved;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StockReservation, $this>
     */
    public function stockReservations(): HasMany
    {
        // stocks.product_id is unique; composite FKs enforce the same store for this product.
        return $this->hasMany(StockReservation::class, 'product_id', 'product_id');
    }

    protected function available(): Attribute
    {
        return Attribute::get(fn () => $this->quantity - $this->reserved);
    }
}
