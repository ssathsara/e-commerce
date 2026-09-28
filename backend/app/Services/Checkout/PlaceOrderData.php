<?php

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;

/**
 * Validated checkout input. Deliberately contains NO prices or totals:
 * those are always computed server-side.
 */
final readonly class PlaceOrderData
{
    public function __construct(
        public string $idempotencyKey,
        public int $shippingAddressId,
        public int $billingAddressId,
        public string $shippingMethod,
        public PaymentMethod $paymentMethod,
        public ?string $paymentToken,
        public ?string $notes,
    ) {}
}
