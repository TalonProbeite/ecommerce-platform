<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class StockReservation extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $fillable = [
        'order_id', 'product_id', 'store_id', 'quantity', 'status',
        'rental_start_at', 'rental_end_at', 'issued_at', 'returned_at',
    ];

    // Preserve the supplied offset when Eloquent writes PostgreSQL TIMESTAMPTZ values.
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => 'string',
            'rental_start_at' => 'immutable_datetime',
            'rental_end_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
        ];
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
}
