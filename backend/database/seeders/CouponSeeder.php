<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['WELCOME10', '10% off your order', CouponType::Percentage, 10, null, 5000, null, 1, now()->addMonths(6)],
            ['SAVE20', '$20 off orders over $150', CouponType::Fixed, 2000, 15000, null, 500, null, now()->addMonths(3)],
            ['SUMMER25', '25% off, up to $75', CouponType::Percentage, 25, 5000, 7500, 200, 2, now()->addMonth()],
            ['FLASH50', '$50 off orders over $400 (limited)', CouponType::Fixed, 5000, 40000, null, 3, 1, now()->addWeeks(2)],
            ['EXPIRED15', 'Expired spring promotion', CouponType::Percentage, 15, null, null, null, null, now()->subDays(10)],
        ];

        foreach ($coupons as [$code, $description, $type, $value, $min, $max, $limit, $perUser, $expires]) {
            Coupon::query()->updateOrCreate(['code' => $code], [
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'min_order_amount' => $min,
                'max_discount_amount' => $max,
                'starts_at' => now()->subMonth(),
                'expires_at' => $expires,
                'usage_limit' => $limit,
                'usage_limit_per_user' => $perUser,
                'is_active' => true,
            ]);
        }

        Coupon::query()->updateOrCreate(['code' => 'VIP30'], [
            'description' => 'Inactive VIP campaign',
            'type' => CouponType::Percentage,
            'value' => 30,
            'is_active' => false,
        ]);
    }
}
