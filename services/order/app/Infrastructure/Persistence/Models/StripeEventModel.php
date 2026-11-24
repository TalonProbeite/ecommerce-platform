<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class StripeEventModel extends OrderBaseModel
{
    protected $table = 'stripe_events';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'type',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'immutable_datetime',
        ];
    }
}
