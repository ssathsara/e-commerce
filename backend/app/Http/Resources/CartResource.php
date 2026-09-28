<?php

namespace App\Http\Resources;

use App\Services\Cart\CartLine;
use App\Services\Cart\CartSummary;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises a server-computed CartSummary.
 *
 * @property CartSummary $resource
 */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;
        $cart = $summary->cart;

        return [
            'id' => $cart?->id,
            // Guest carts only: the SPA stores this and sends it back as X-Cart-Token.
            'token' => $cart && $cart->user_id === null ? $cart->token : null,
            'items' => array_map(fn (CartLine $line) => $this->line($line), $summary->lines),
            'item_count' => $summary->itemCount(),
            'coupon' => $summary->coupon ? [
                'code' => $summary->coupon->code,
                'description' => $summary->coupon->description,
                'is_applied' => $summary->couponError === null,
                'error' => $summary->couponError,
            ] : null,
            'shipping_method' => $summary->shippingMethod,
            'totals' => [
                'subtotal' => Money::toMajor($summary->totals->subtotal),
                'discount' => Money::toMajor($summary->totals->discount),
                'shipping' => Money::toMajor($summary->totals->shipping),
                'tax' => Money::toMajor($summary->totals->tax),
                'total' => Money::toMajor($summary->totals->total),
            ],
            'tax_rate' => (float) config('shop.tax_rate'),
            'currency' => config('shop.currency'),
            'has_issues' => $summary->hasIssues(),
        ];
    }

    private function line(CartLine $line): array
    {
        $item = $line->item;
        $product = $item->product;
        $variant = $item->variant;

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'unit_price' => Money::toMajor($line->unitPrice),
            'line_total' => Money::toMajor($line->lineTotal),
            'available_stock' => $line->availableStock,
            'issue' => $line->issue,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'image' => $product->primaryImage ? (new ProductImageResource($product->primaryImage))->resolve() : null,
            ] : null,
            'variant' => $variant ? [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'options' => $variant->options ?? (object) [],
            ] : null,
        ];
    }
}
