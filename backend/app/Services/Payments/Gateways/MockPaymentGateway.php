<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\ChargeRequest;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Safe, offline test gateway. It mirrors the Stripe test-token convention so the
 * checkout UI can exercise success and failure paths without real credentials:
 *
 *   tok_visa, tok_mastercard  -> payment succeeds
 *   tok_chargeDeclined        -> card declined
 *   tok_insufficientFunds     -> insufficient funds
 *
 * It never sees or stores card numbers.
 */
class MockPaymentGateway implements PaymentGatewayInterface
{
    public const SUCCESS_TOKENS = ['tok_visa', 'tok_mastercard'];

    public const FAILURE_TOKENS = [
        'tok_chargeDeclined' => 'Your card was declined.',
        'tok_insufficientFunds' => 'Your card has insufficient funds.',
    ];

    public function name(): string
    {
        return 'mock';
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        // Emulate provider-side idempotency: the same key always yields the same result.
        return Cache::remember(
            'mock-gateway:charge:'.$request->idempotencyKey,
            now()->addDay(),
            fn () => $this->performCharge($request),
        );
    }

    public function refund(Payment $payment): PaymentResult
    {
        return PaymentResult::success('mock_re_'.Str::lower(Str::random(20)), [
            'refunded_amount' => $payment->amount,
        ]);
    }

    private function performCharge(ChargeRequest $request): PaymentResult
    {
        if ($request->amount <= 0) {
            return PaymentResult::failure('Invalid payment amount.');
        }

        if (in_array($request->paymentToken, self::SUCCESS_TOKENS, true)) {
            return PaymentResult::success('mock_ch_'.Str::lower(Str::random(24)), [
                'brand' => $request->paymentToken === 'tok_visa' ? 'visa' : 'mastercard',
                'last4' => $request->paymentToken === 'tok_visa' ? '4242' : '4444',
            ]);
        }

        return PaymentResult::failure(self::FAILURE_TOKENS[$request->paymentToken] ?? 'Invalid payment details.');
    }
}
