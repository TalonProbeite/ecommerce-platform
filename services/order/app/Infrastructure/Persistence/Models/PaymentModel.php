<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentModel extends OrderBaseModel
{
    use HasUuidV7;

    protected $table = 'payments';

    protected $fillable = [
        'id',
        'order_id',
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'status',
        'failure_code',
        'succeeded_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'succeeded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(RefundModel::class, 'payment_id');
    }
}
