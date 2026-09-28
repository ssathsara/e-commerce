<?php

namespace App\Services\Checkout;

/**
 * Immutable totals in cents, produced only by TotalsCalculator.
 */
final readonly class OrderTotals
{
    public function __construct(
        public int $subtotal,
        public int $discount,
        public int $shipping,
        public int $tax,
        public int $total,
    ) {}
}
