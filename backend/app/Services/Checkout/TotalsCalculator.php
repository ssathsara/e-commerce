<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Support\Money;

/**
 * Single source of truth for price calculation, shared by the cart summary and
 * order placement so the customer is always charged what they were shown.
 *
 *   discount = coupon discount on subtotal
 *   shipping = method rate (free above threshold for eligible methods), on subtotal - discount
 *   tax      = tax_rate% of (subtotal - discount); shipping is not taxed
 *   total    = subtotal - discount + shipping + tax
 */
class TotalsCalculator
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function calculate(int $subtotal, ?Coupon $coupon, string $shippingMethod): OrderTotals
    {
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $discounted = $subtotal - $discount;
        $shipping = $subtotal > 0 ? $this->shipping->cost($shippingMethod, $discounted) : 0;
        $tax = Money::percentOf($discounted, (float) config('shop.tax_rate'));

        return new OrderTotals(
            subtotal: $subtotal,
            discount: $discount,
            shipping: $shipping,
            tax: $tax,
            total: $discounted + $shipping + $tax,
        );
    }
}
