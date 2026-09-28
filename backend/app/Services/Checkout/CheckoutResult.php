<?php

namespace App\Services\Checkout;

use App\Models\Order;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        /** False when an earlier request with the same idempotency key already created the order. */
        public bool $created,
        /** Set when an online payment was attempted and declined. The order still exists and can be paid again. */
        public ?string $paymentError = null,
    ) {}
}
