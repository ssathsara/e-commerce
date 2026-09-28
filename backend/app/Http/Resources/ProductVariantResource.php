<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the variant's "product" relation to be set (for price inheritance).
 *
 * @mixin \App\Models\ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'options' => $this->options ?? (object) [],
            'price' => Money::toMajor($this->effectivePrice()),
            'compare_at_price' => Money::toMajor($this->effectiveCompareAtPrice()),
            // Raw override values for the admin form (null = inherits product price).
            'price_override' => Money::toMajor($this->price),
            'compare_at_price_override' => Money::toMajor($this->compare_at_price),
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
