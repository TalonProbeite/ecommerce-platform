<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderModel extends OrderBaseModel
{
    use HasUuidV7;

    protected $table = 'orders';

    protected $fillable = [
        'id',
        'customer_id',
        'store_id',
        'status',
        'cancel_reason',
        'currency',
        'subtotal_amount',
        'deposit_amount',
        'idempotency_key',
        'request_fingerprint',
        'version',
        'cancelled_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'deposit_amount' => 'integer',
            'version' => 'integer',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEventModel::class, 'order_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrderCommentModel::class, 'order_id');
    }
}
