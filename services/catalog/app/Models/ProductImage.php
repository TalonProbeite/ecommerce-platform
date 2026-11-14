<?php

namespace App\Models;

use App\Models\Concerns\UsesPreciseDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

/**
 * @mixin IdeHelperProductImage
 */
class ProductImage extends Model
{
    use HasUuids;
    use UsesPreciseDates;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

    protected $fillable = ['id', 'product_id', 'object_key', 'mime_type', 'size', 'is_main'];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk((string) config('catalog.product_images.disk'))->url($this->object_key);
    }
}
