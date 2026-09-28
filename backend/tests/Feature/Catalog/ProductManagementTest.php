<?php

namespace Tests\Feature\Catalog;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aurora Desk Lamp',
            'sku' => 'lamp-001',
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'short_description' => 'A warm, dimmable lamp.',
            'description' => 'Long description.',
            'price' => 49.99,
            'compare_at_price' => 59.99,
            'stock_quantity' => 12,
            'low_stock_threshold' => 3,
            'status' => 'active',
            'is_featured' => true,
            'seo_title' => 'Aurora Desk Lamp',
        ], $overrides);
    }

    public function test_admin_can_create_product_with_opening_stock(): void
    {
        $response = $this->signIn($this->admin())->postJson('/api/v1/admin/products', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('data.slug', 'aurora-desk-lamp')
            ->assertJsonPath('data.sku', 'LAMP-001')
            ->assertJsonPath('data.price', 49.99)
            ->assertJsonPath('data.stock_quantity', 12)
            ->assertJsonPath('data.discount_percentage', 17);

        $product = Product::firstWhere('sku', 'LAMP-001');
        $this->assertSame(4999, $product->price); // stored in cents
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovementType::Purchase->value,
            'quantity' => 12,
            'stock_after' => 12,
        ]);
    }

    public function test_product_without_stock_is_created_out_of_stock(): void
    {
        $this->signIn($this->admin())->postJson('/api/v1/admin/products', $this->payload(['stock_quantity' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.status', ProductStatus::OutOfStock->value);
    }

    public function test_invalid_product_data_is_rejected(): void
    {
        $this->signIn($this->admin())->postJson('/api/v1/admin/products', [
            'name' => '',
            'sku' => 'bad sku!',
            'category_id' => 999999,
            'price' => -5,
            'compare_at_price' => 1,
            'status' => 'published',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'category_id', 'price', 'status', 'low_stock_threshold']);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $this->product(attributes: ['sku' => 'DUP-1']);

        $this->signIn($this->admin())->postJson('/api/v1/admin/products', $this->payload(['sku' => 'dup-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_slugs_are_unique(): void
    {
        $admin = $this->admin();
        $this->signIn($admin)->postJson('/api/v1/admin/products', $this->payload(['sku' => 'A-1']))->assertCreated();
        $this->signIn($admin)->postJson('/api/v1/admin/products', $this->payload(['sku' => 'A-2']))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'aurora-desk-lamp-2');
    }

    public function test_admin_can_update_product_with_variants(): void
    {
        $admin = $this->admin();
        $productId = $this->signIn($admin)->postJson('/api/v1/admin/products', $this->payload(['stock_quantity' => 5]))->json('data.id');

        $response = $this->signIn($admin)->putJson("/api/v1/admin/products/{$productId}", $this->payload([
            'name' => 'Aurora Lamp v2',
            'price' => 55,
            'variants' => [
                ['name' => 'Black', 'sku' => 'LAMP-BLK', 'options' => ['Color' => 'Black'], 'stock_quantity' => 4, 'is_active' => true],
                ['name' => 'White', 'sku' => 'LAMP-WHT', 'options' => ['Color' => 'White'], 'price' => 60, 'stock_quantity' => 6, 'is_active' => true],
            ],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.name', 'Aurora Lamp v2')
            ->assertJsonCount(2, 'data.variants')
            // Aggregate stock = sum of active variants; opening product stock was moved out.
            ->assertJsonPath('data.stock_quantity', 10)
            ->assertJsonPath('data.variants.1.price', 60);

        $this->assertDatabaseHas('inventory_movements', ['product_id' => $productId, 'product_variant_id' => null, 'quantity' => -5, 'type' => 'adjustment']);
    }

    public function test_status_can_be_toggled_but_out_of_stock_cannot_be_set_manually(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->signIn($admin)->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->signIn($admin)->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'out_of_stock'])
            ->assertStatus(422);
    }

    public function test_deleting_product_soft_deletes_and_hides_it_from_storefront(): void
    {
        $product = $this->product();

        $this->signIn($this->admin())->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->assertSoftDeleted($product);
        $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound();
    }

    public function test_admin_product_list_supports_search_filters_and_pagination(): void
    {
        $this->product(attributes: ['name' => 'Blue Kettle', 'sku' => 'KET-1']);
        $this->product(stock: 0, attributes: ['name' => 'Red Kettle', 'sku' => 'KET-2', 'status' => ProductStatus::OutOfStock]);
        $this->product(attributes: ['name' => 'Toaster', 'sku' => 'TOA-1']);

        $response = $this->signIn($this->admin())->getJson('/api/v1/admin/products?search=kettle&stock=out&per_page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.name', 'Red Kettle');
    }

    public function test_images_can_be_uploaded_reordered_set_primary_and_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $response = $this->signIn($admin)->post("/api/v1/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg', 400, 400), UploadedFile::fake()->image('b.png', 400, 400)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data');
        [$first, $second] = $response->json('data');
        $this->assertTrue($first['is_primary']);
        Storage::disk('public')->assertExists($product->images()->pluck('path')->all());

        $this->signIn($admin)->patchJson("/api/v1/admin/products/{$product->id}/images/{$second['id']}", ['is_primary' => true])
            ->assertOk()->assertJsonPath('data.0.id', $second['id']);

        $this->signIn($admin)->putJson("/api/v1/admin/products/{$product->id}/images/reorder", ['image_ids' => [$second['id'], $first['id']]])->assertOk();

        $path = $product->images()->whereKey($second['id'])->value('path');
        $this->signIn($admin)->deleteJson("/api/v1/admin/products/{$product->id}/images/{$second['id']}")->assertOk();
        Storage::disk('public')->assertMissing($path);
        // The remaining image is promoted to primary.
        $this->assertTrue($product->images()->first()->is_primary);
    }

    public function test_non_image_and_tiny_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $product = $this->product();

        $this->signIn($this->admin())->post("/api/v1/admin/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
                UploadedFile::fake()->image('tiny.jpg', 20, 20),
            ],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['images.0', 'images.1']);
    }

    public function test_image_of_another_product_cannot_be_modified(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $owner = $this->product();
        $other = $this->product();
        $imageId = $this->signIn($admin)->post("/api/v1/admin/products/{$owner->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg', 300, 300)],
        ], ['Accept' => 'application/json'])->json('data.0.id');

        $this->signIn($admin)->deleteJson("/api/v1/admin/products/{$other->id}/images/{$imageId}")->assertNotFound();
    }
}
