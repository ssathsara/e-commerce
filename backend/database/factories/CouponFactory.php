<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'description' => 'Test coupon',
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_order_amount' => null,
            'max_discount_amount' => null,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'is_active' => true,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->state(fn () => ['type' => CouponType::Fixed, 'value' => $cents]);
    }

    public function percentage(int $percent): static
    {
        return $this->state(fn () => ['type' => CouponType::Percentage, 'value' => $percent]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
