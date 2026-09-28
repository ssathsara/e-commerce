<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full public product page representation.
 *
 * @mixin \App\Models\Product
 */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variants = $this->whenLoaded('activeVariants', function () {
            $this->activeVariants->each->setRelation('product', $this->resource);

            return ProductVariantResource::collection($this->activeVariants);
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => Money::toMajor($this->price),
            'compare_at_price' => Money::toMajor($this->compare_at_price),
            'discount_percentage' => $this->discountPercentage(),
            'status' => $this->status->value,
            'in_stock' => $this->isPurchasable(),
            'stock_quantity' => $this->stock_quantity,
            'is_low_stock' => $this->isLowStock(),
            'rating_average' => (float) $this->rating_average,
            'rating_count' => $this->rating_count,
            'weight' => $this->weight,
            'dimensions' => [
                'length' => $this->length,
                'width' => $this->width,
                'height' => $this->height,
            ],
            'seo' => [
                'title' => $this->seo_title ?: $this->name,
                'description' => $this->seo_description ?: $this->short_description,
            ],
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? new BrandResource($this->brand) : null),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => $variants,
        ];
    }
}
