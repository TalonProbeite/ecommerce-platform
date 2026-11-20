<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RefundModel extends OrderBaseModel
{
    use HasUuidV7;

    protected $table = 'refunds';

    protected $fillable = [
        'id',
        'order_id',
        'order_item_id',
        'payment_id',
        'provider_refund_id',
        'amount',
        'reason',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItemModel::class, 'order_item_id');
    }
}
