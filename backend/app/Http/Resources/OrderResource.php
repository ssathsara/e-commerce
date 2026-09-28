<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing order representation. AdminOrderResource extends it with internal fields.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'shipping_method' => $this->shipping_method,
            'shipping_method_label' => config("shop.shipping.methods.{$this->shipping_method}.name", $this->shipping_method),
            'currency' => $this->currency,
            'subtotal' => Money::toMajor($this->subtotal),
            'discount_total' => Money::toMajor($this->discount_total),
            'shipping_total' => Money::toMajor($this->shipping_total),
            'tax_total' => Money::toMajor($this->tax_total),
            'grand_total' => Money::toMajor($this->grand_total),
            'coupon_code' => $this->coupon_code,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'customer_email' => $this->customer_email,
            'notes' => $this->notes,
            'tracking_number' => $this->tracking_number,
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'can_cancel' => $this->status->isCancellableByCustomer(),
            'can_pay' => $this->payment_method->isOnline()
                && $this->payment_status !== PaymentStatus::Paid
                && ! in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
