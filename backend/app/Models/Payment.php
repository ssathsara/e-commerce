<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt for an order. Only non-sensitive gateway metadata is stored.
 */
class Payment extends Model
{
    protected $fillable = [
        'gateway',
        'method',
        'status',
        'amount',
        'currency',
        'transaction_reference',
        'idempotency_key',
        'failure_reason',
        'meta',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
