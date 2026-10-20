<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Store extends Model
{
    use HasUuids;
    use SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $fillable = ['name', 'active', 'description', 'location'];


    protected function casts(): array
    {
        return [
            'name' => 'string',
            'active' => 'boolean',
            'description' => 'string',
            'location' => 'string',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
