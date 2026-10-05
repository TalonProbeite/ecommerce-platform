<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Ramsey\Uuid\Uuid;

class Stock extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved' => 'integer',
        ];
    }
    

    public function getAvailableAttribute(): int
    {
        return $this->quantity - $this->reserved;
    }
}