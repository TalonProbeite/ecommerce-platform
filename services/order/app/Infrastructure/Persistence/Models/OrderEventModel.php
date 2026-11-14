<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class OrderEventModel extends OrderBaseModel
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected $table = 'order_events';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_role',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
