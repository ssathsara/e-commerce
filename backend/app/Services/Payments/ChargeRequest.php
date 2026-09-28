<?php

namespace App\Services\Payments;

final readonly class ChargeRequest
{
    /**
     * @param  int  $amount  Amount in minor units (cents).
     * @param  string  $paymentToken  Opaque token produced by the provider's client-side SDK.
     *                                Raw card data never reaches this server.
     */
    public function __construct(
        public int $amount,
        public string $currency,
        public string $paymentToken,
        public string $idempotencyKey,
        public string $description,
        public array $metadata = [],
    ) {}
}
