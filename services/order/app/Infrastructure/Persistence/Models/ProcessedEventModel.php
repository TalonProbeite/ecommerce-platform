<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class ProcessedEventModel extends OrderBaseModel
{
    protected $table = 'processed_events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'event_id',
        'routing_key',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'immutable_datetime',
        ];
    }
}
