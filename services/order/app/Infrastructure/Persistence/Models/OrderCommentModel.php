<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Infrastructure\Persistence\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderCommentModel extends OrderBaseModel
{
    use HasUuidV7;

    protected $table = 'order_comments';

    protected $fillable = [
        'id',
        'order_id',
        'author_id',
        'author_role',
        'body',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
