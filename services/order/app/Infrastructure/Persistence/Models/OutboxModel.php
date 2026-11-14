<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class OutboxModel extends OrderBaseModel
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected $table = 'outbox';

    protected $fillable = [
        'event_id',
        'routing_key',
        'aggregate_key',
        'payload',
        'occurred_at',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
