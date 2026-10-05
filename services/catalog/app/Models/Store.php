<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Store extends Model
{
    use HasUuids, SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $guarded = [];


    protected function casts(): array
    {
        return [
            'name'=> 'string',
            'active' => 'boolean'
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
} 
