<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Contract every payment provider implements. The order/checkout code depends only
 * on this interface, so adding Stripe or PayPal means adding one implementation and
 * registering it in AppServiceProvider, without touching the order system.
 *
 * Implementations must be idempotent on ChargeRequest::$idempotencyKey (real gateways
 * such as Stripe support idempotency keys natively).
 */
interface PaymentGatewayInterface
{
    /** Short identifier stored on payments (e.g. "mock", "stripe"). */
    public function name(): string;

    public function charge(ChargeRequest $request): PaymentResult;

    /** Refund a previously captured payment in full. */
    public function refund(Payment $payment): PaymentResult;
}
