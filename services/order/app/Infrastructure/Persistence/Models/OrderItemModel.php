<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemModel extends OrderBaseModel
{
    use HasUuidV7;

    protected $table = 'order_items';

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'rental_start_at',
        'rental_end_at',
        'billed_hours',
        'price_per_hour',
        'deposit_per_unit',
        'line_total',
        'deposit_total',
        'item_status',
        'issued_at',
        'issued_by',
        'returned_at',
        'returned_by',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'rental_start_at' => 'immutable_datetime',
            'rental_end_at' => 'immutable_datetime',
            'billed_hours' => 'integer',
            'price_per_hour' => 'integer',
            'deposit_per_unit' => 'integer',
            'line_total' => 'integer',
            'deposit_total' => 'integer',
            'issued_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
