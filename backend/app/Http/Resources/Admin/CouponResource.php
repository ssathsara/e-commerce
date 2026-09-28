<?php

namespace App\Http\Resources\Admin;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Coupon */
class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type->value,
            // Percent for percentage coupons, major currency units for fixed coupons.
            'value' => $this->type === CouponType::Percentage ? $this->value : Money::toMajor($this->value),
            'min_order_amount' => Money::toMajor($this->min_order_amount),
            'max_discount_amount' => Money::toMajor($this->max_discount_amount),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'times_used' => $this->whenCounted('usages'),
            'is_active' => $this->is_active,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
