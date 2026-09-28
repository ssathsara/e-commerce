<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * value: whole percent (1-100) for percentage coupons, cents for fixed coupons.
 * min_order_amount / max_discount_amount: cents.
 */
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'usage_limit_per_user',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'min_order_amount' => 'integer',
            'max_discount_amount' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are case-insensitive and stored upper-case. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    /** Discount in cents for the given (pre-discount) subtotal in cents. */
    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === CouponType::Percentage
            ? (int) round($subtotal * $this->value / 100)
            : $this->value;

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return max(0, min($discount, $subtotal));
    }
}
