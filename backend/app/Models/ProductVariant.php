<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchasable variation of a product. price/compare_at_price are cents and
 * NULL means "inherit from the product".
 */
class ProductVariant extends Model
{
    use HasFactory;

    /** stock_quantity is changed only through InventoryService. */
    protected $fillable = [
        'name',
        'sku',
        'options',
        'price',
        'compare_at_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function effectivePrice(): int
    {
        return $this->price ?? $this->product->price;
    }

    public function effectiveCompareAtPrice(): ?int
    {
        return $this->price !== null ? $this->compare_at_price : $this->product->compare_at_price;
    }
}
