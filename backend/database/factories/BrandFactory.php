<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement(['Labs', 'Co', 'Works', 'Studio']),
            'description' => fake()->sentence(10),
            'is_active' => true,
        ];
    }
}
