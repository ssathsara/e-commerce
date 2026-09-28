<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductImageResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin representation, including internal fields used by the edit form.
 *
 * @mixin Product
 */
class AdminProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => Money::toMajor($this->price),
            'compare_at_price' => Money::toMajor($this->compare_at_price),
            'discount_percentage' => $this->discountPercentage(),
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_low_stock' => $this->isLowStock(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_featured' => $this->is_featured,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'rating_average' => (float) $this->rating_average,
            'rating_count' => $this->rating_count,
            'sales_count' => $this->sales_count,
            'variants_count' => $this->whenCounted('variants'),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null),
            'image' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage ? new ProductImageResource($this->primaryImage) : null),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => $this->whenLoaded('variants', function () {
                $this->variants->each->setRelation('product', $this->resource);

                return ProductVariantResource::collection($this->variants);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
