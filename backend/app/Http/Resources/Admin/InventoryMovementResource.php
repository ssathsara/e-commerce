<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryMovement */
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'note' => $this->note,
            'product' => $this->whenLoaded('product', fn () => $this->product ? ['id' => $this->product->id, 'name' => $this->product->name, 'sku' => $this->product->sku] : null),
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? ['id' => $this->variant->id, 'name' => $this->variant->name, 'sku' => $this->variant->sku] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? ['id' => $this->order->id, 'order_number' => $this->order->order_number] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
