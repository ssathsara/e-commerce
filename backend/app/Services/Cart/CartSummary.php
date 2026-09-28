<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\Coupon;
use App\Services\Checkout\OrderTotals;

/**
 * Server-computed view of a cart. Nothing in here is ever taken from the client.
 */
final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public ?Cart $cart,
        public array $lines,
        public OrderTotals $totals,
        public ?Coupon $coupon,
        public ?string $couponError,
        public string $shippingMethod,
    ) {}

    public function itemCount(): int
    {
        return array_sum(array_map(fn (CartLine $line) => $line->item->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /** Checkout is blocked while any line has a problem (unavailable, not enough stock). */
    public function hasIssues(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->issue !== null) {
                return true;
            }
        }

        return false;
    }
}
