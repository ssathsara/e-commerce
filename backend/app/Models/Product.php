<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Monetary attributes (price, compare_at_price) are integers in cents.
 */
class Product extends Model
{
    use HasFactory, HasUniqueSlug, SoftDeletes;

    /**
     * stock_quantity, rating_* and sales_count are deliberately NOT fillable:
     * they are changed only through InventoryService / ReviewService / OrderService.
     */
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'low_stock_threshold',
        'status',
        'is_featured',
        'weight',
        'length',
        'width',
        'height',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_featured' => 'boolean',
            'weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'sales_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /* ----------------------------------------------------------------- */
    /* Domain helpers */
    /* ----------------------------------------------------------------- */

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /** Status allows purchasing and there is stock available. */
    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Active && $this->stock_quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    /** Discount against the compare-at price, derived rather than stored. */
    public function discountPercentage(): int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return 0;
        }

        return (int) round((1 - $this->price / $this->compare_at_price) * 100);
    }

    /* ----------------------------------------------------------------- */
    /* Scopes */
    /* ----------------------------------------------------------------- */

    /** Products that may appear on the public storefront. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereIn('status', ProductStatus::publiclyVisible())
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $escaped = addcslashes($term, '%_\\');

        return $query->where(function (Builder $q) use ($escaped) {
            $q->where('name', 'like', "%{$escaped}%")
                ->orWhere('sku', 'like', "{$escaped}%")
                ->orWhere('short_description', 'like', "%{$escaped}%");
        });
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', 0);
    }
}
