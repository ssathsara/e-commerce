<?php

namespace App\Http\Resources\Admin;

use App\Models\CouponUsage;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CouponUsage */
class CouponUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_amount' => Money::toMajor($this->discount_amount),
            'customer' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? ['id' => $this->order->id, 'order_number' => $this->order->order_number] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
