<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factories run unguarded, so stock can be set directly here. Application code
 * changes stock only through InventoryService.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->numberBetween(500, 50000);

        return [
            'category_id' => Category::factory(),
            'brand_id' => null,
            'name' => ucwords(fake()->unique()->words(3, true)),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'price' => $price,
            'compare_at_price' => null,
            'stock_quantity' => 50,
            'low_stock_threshold' => 5,
            'status' => ProductStatus::Active,
            'is_featured' => false,
        ];
    }

    public function stock(int $quantity): static
    {
        return $this->state(fn () => ['stock_quantity' => $quantity]);
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function price(int $cents): static
    {
        return $this->state(fn () => ['price' => $cents, 'compare_at_price' => null]);
    }
}
