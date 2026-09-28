<?php

namespace App\Services\Payments;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $reference = null,
        public ?string $failureReason = null,
        public array $meta = [],
    ) {}

    public static function success(string $reference, array $meta = []): self
    {
        return new self(true, $reference, null, $meta);
    }

    public static function failure(string $reason, array $meta = []): self
    {
        return new self(false, null, $reason, $meta);
    }
}
