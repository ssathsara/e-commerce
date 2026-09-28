<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'amount' => Money::toMajor($this->amount),
            'currency' => $this->currency,
            'transaction_reference' => $this->transaction_reference,
            'failure_reason' => $this->failure_reason,
            // Only display-safe card details (brand/last4) are ever stored.
            'card' => isset($this->meta['brand']) ? ['brand' => $this->meta['brand'], 'last4' => $this->meta['last4'] ?? null] : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
