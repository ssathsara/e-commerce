<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact product representation for grids and lists.
 *
 * @mixin \App\Models\Product
 */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'price' => Money::toMajor($this->price),
            'compare_at_price' => Money::toMajor($this->compare_at_price),
            'discount_percentage' => $this->discountPercentage(),
            'status' => $this->status->value,
            'in_stock' => $this->isPurchasable(),
            'is_low_stock' => $this->isLowStock(),
            'is_featured' => $this->is_featured,
            'has_variants' => ($this->active_variants_count ?? $this->variants_count ?? 0) > 0,
            'rating_average' => (float) $this->rating_average,
            'rating_count' => $this->rating_count,
            'image' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage ? new ProductImageResource($this->primaryImage) : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
        ];
    }
}
