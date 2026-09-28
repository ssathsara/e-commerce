<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $size = fake()->randomElement(['S', 'M', 'L', 'XL']);

        return [
            'product_id' => Product::factory(),
            'name' => "Size {$size}",
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####-??')),
            'options' => ['Size' => $size],
            'price' => null,
            'stock_quantity' => 10,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function stock(int $quantity): static
    {
        return $this->state(fn () => ['stock_quantity' => $quantity]);
    }
}
