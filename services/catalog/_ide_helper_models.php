<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $parent_id
 * @property string $store_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \App\Models\Store|null $store
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCategory {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_per_hour
 * @property int $deposit_amount
 * @property numeric $rating
 * @property int $reviews_count
 * @property bool $is_active
 * @property string $store_id
 * @property string $category_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductAttribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductImage> $images
 * @property-read int|null $images_count
 * @property-read \App\Models\Stock|null $stock
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockReservation> $stockReservations
 * @property-read int|null $stock_reservations_count
 * @property-read \App\Models\Store|null $store
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDepositAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePricePerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereReviewsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProduct {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $product_id
 * @property string $name
 * @property string $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttribute whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProductAttribute {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $product_id
 * @property string $path
 * @property bool $is_main
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereIsMain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductImage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProductImage {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $product_id
 * @property string $store_id
 * @property int $quantity
 * @property int $reserved
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $available
 * @property-read \App\Models\Product|null $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockReservation> $stockReservations
 * @property-read int|null $stock_reservations_count
 * @property-read \App\Models\Store|null $store
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereReserved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStock {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $order_id
 * @property string $product_id
 * @property string $store_id
 * @property int $quantity
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $rental_start_at
 * @property \Carbon\CarbonImmutable|null $rental_end_at
 * @property \Carbon\CarbonImmutable|null $issued_at
 * @property \Carbon\CarbonImmutable|null $returned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Store|null $store
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereRentalEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereRentalStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereReturnedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockReservation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStockReservation {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $location
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Stock> $stocks
 * @property-read int|null $stocks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Store withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperStore {}
}
