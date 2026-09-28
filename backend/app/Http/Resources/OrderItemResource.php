<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_slug' => $this->whenLoaded('product', fn () => $this->product?->trashed() ? null : $this->product?->slug),
            'image' => $this->whenLoaded('product', fn () => $this->product?->relationLoaded('primaryImage') && $this->product->primaryImage
                ? (new ProductImageResource($this->product->primaryImage))->resolve()
                : null),
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'sku' => $this->sku,
            'unit_price' => Money::toMajor($this->unit_price),
            'quantity' => $this->quantity,
            'line_total' => Money::toMajor($this->line_total),
        ];
    }
}
