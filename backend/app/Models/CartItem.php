<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Authoritative unit price in cents, taken from the catalogue. */
    public function unitPrice(): int
    {
        return $this->variant ? $this->variant->effectivePrice() : $this->product->price;
    }

    /** Stock available for this line (variant stock when a variant is selected). */
    public function availableStock(): int
    {
        return $this->variant ? $this->variant->stock_quantity : $this->product->stock_quantity;
    }
}
