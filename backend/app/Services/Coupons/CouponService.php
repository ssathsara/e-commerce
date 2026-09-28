<?php

namespace App\Services\Coupons;

use App\Exceptions\BusinessRuleException;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;

class CouponService
{
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Validate that the coupon can be applied to a subtotal (cents) by the given user.
     *
     * With $lock = true the coupon row is locked FOR UPDATE, which serialises
     * concurrent checkouts so usage limits cannot be exceeded. Must then be called
     * inside a transaction.
     *
     * @throws BusinessRuleException
     */
    public function validate(Coupon $coupon, int $subtotal, ?User $user, bool $lock = false): Coupon
    {
        if ($lock) {
            $coupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->first()
                ?? throw $this->invalid('This coupon is no longer available.');
        }

        if (! $coupon->is_active) {
            throw $this->invalid('This coupon is not active.');
        }

        if (! $coupon->hasStarted()) {
            throw $this->invalid('This coupon is not valid yet.');
        }

        if ($coupon->isExpired()) {
            throw $this->invalid('This coupon has expired.');
        }

        if ($coupon->min_order_amount !== null && $subtotal < $coupon->min_order_amount) {
            $minimum = number_format(Money::toMajor($coupon->min_order_amount), 2);

            throw $this->invalid("A minimum order of {$minimum} is required for this coupon.");
        }

        if ($coupon->usage_limit !== null && $coupon->usages()->count() >= $coupon->usage_limit) {
            throw $this->invalid('This coupon has reached its usage limit.');
        }

        if ($user !== null && $coupon->usage_limit_per_user !== null) {
            $usedByUser = $coupon->usages()->where('user_id', $user->id)->count();

            if ($usedByUser >= $coupon->usage_limit_per_user) {
                throw $this->invalid('You have already used this coupon the maximum number of times.');
            }
        }

        return $coupon;
    }

    public function recordUsage(Coupon $coupon, Order $order, int $discount): CouponUsage
    {
        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $discount,
        ]);
    }

    /** A cancelled order gives its coupon usage back. */
    public function releaseUsage(Order $order): void
    {
        CouponUsage::query()->where('order_id', $order->id)->delete();
    }

    private function invalid(string $message): BusinessRuleException
    {
        return BusinessRuleException::unprocessable($message, 'coupon_code');
    }
}
