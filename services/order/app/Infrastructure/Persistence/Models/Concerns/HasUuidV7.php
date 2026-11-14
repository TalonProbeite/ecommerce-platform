<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuidV7
{
    protected static function bootHasUuidV7(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getKey())) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid7());
            }
        });
    }
}
