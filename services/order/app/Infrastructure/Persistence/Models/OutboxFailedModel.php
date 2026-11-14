<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

final class OutboxFailedModel extends OrderBaseModel
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected $table = 'outbox_failed';

    protected $fillable = [
        'event_id',
        'routing_key',
        'aggregate_key',
        'payload',
        'occurred_at',
        'published_at',
        'failed_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
