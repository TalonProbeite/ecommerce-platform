<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class ProductSnapshotModel extends OrderBaseModel
{
    protected $table = 'product_snapshots';

    protected $primaryKey = 'product_id';

    protected $fillable = [
        'product_id',
        'store_id',
        'category_id',
        'name',
        'price_per_hour',
        'deposit_amount',
        'is_active',
        'source_occurred_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'integer',
            'deposit_amount' => 'integer',
            'is_active' => 'boolean',
            'source_occurred_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
