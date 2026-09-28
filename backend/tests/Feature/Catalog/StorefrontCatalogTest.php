<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    public function test_listing_only_shows_visible_products_with_pagination_meta(): void
    {
        $this->product(attributes: ['name' => 'Visible']);
        $this->product(stock: 0, attributes: ['name' => 'Sold Out', 'status' => ProductStatus::OutOfStock]);
        $this->product(attributes: ['name' => 'Draft', 'status' => ProductStatus::Draft]);
        $this->product(attributes: ['name' => 'Hidden', 'status' => ProductStatus::Inactive]);
        Product::factory()->for(Category::factory()->inactive())->create(['name' => 'Inactive Category']);

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.total', 2);

        $this->assertEqualsCanonicalizing(['Visible', 'Sold Out'], array_column($response->json('data'), 'name'));
    }

    public function test_category_filter_includes_subcategories(): void
    {
        $parent = Category::factory()->create(['slug' => 'electronics']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        Product::factory()->for($child)->create(['name' => 'Phone']);
        Product::factory()->for($parent)->create(['name' => 'Cable']);
        $this->product(attributes: ['name' => 'Elsewhere']);

        $names = array_column($this->getJson('/api/v1/products?category=electronics')->json('data'), 'name');

        $this->assertEqualsCanonicalizing(['Phone', 'Cable'], $names);
    }

    public function test_search_price_stock_brand_and_rating_filters(): void
    {
        $brand = Brand::factory()->create(['slug' => 'acme']);
        $this->product(1500, attributes: ['name' => 'Acme Kettle', 'brand_id' => $brand->id, 'rating_average' => 4.5]);
        $this->product(4500, attributes: ['name' => 'Acme Toaster', 'brand_id' => $brand->id, 'rating_average' => 3.0]);
        $this->product(2500, 0, ['name' => 'Kettle Pro', 'status' => ProductStatus::OutOfStock]);

        $this->assertSame(['Acme Kettle', 'Kettle Pro'], $this->names('search=kettle&sort=price_asc'));
        $this->assertSame(['Acme Kettle', 'Kettle Pro'], $this->names('min_price=10&max_price=30&sort=price_asc'));
        $this->assertSame(['Acme Kettle'], $this->names('search=kettle&in_stock=1'));
        $this->assertSame(['Acme Toaster', 'Acme Kettle'], $this->names('brand=acme&sort=price_desc'));
        $this->assertSame(['Acme Kettle'], $this->names('rating=4'));
    }

    public function test_sorting_by_popularity(): void
    {
        $this->product(attributes: ['name' => 'Rarely bought', 'sales_count' => 1]);
        $this->product(attributes: ['name' => 'Best seller', 'sales_count' => 50]);

        $this->assertSame('Best seller', $this->names('sort=popular')[0]);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->getJson('/api/v1/products?sort=hacker&rating=9')->assertStatus(422)->assertJsonValidationErrors(['sort', 'rating']);
    }

    public function test_product_detail_includes_variants_images_and_hides_inactive_variants(): void
    {
        $product = $this->variantProduct([3, 0]);
        $product->variants()->first()->update(['is_active' => false]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonStructure(['data' => ['images', 'brand', 'category', 'seo' => ['title', 'description']]]);
    }

    public function test_draft_product_detail_returns_404(): void
    {
        $product = $this->product(attributes: ['status' => ProductStatus::Draft]);

        $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_home_and_category_tree_endpoints(): void
    {
        $parent = Category::factory()->create(['is_featured' => true]);
        Category::factory()->create(['parent_id' => $parent->id]);
        $this->product(attributes: ['is_featured' => true, 'category_id' => $parent->id]);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonCount(1, 'data.featured_products')->assertJsonCount(1, 'data.featured_categories');
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data.0.children');
    }

    private function names(string $query): array
    {
        return array_column($this->getJson("/api/v1/products?{$query}")->assertOk()->json('data'), 'name');
    }
}
