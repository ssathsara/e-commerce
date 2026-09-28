<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryBrandTest extends TestCase
{
    public function test_admin_can_create_category_with_image(): void
    {
        Storage::fake('public');
        $parent = Category::factory()->create(['name' => 'Electronics']);

        $response = $this->signIn($this->admin())->post('/api/v1/admin/categories', [
            'name' => 'Phones',
            'parent_id' => $parent->id,
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('phones.jpg', 300, 200),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'phones')
            ->assertJsonPath('data.parent_id', $parent->id);
        $this->assertNotNull($response->json('data.image_url'));
    }

    public function test_category_cannot_become_its_own_descendant(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->signIn($this->admin())->putJson("/api/v1/admin/categories/{$parent->id}", [
            'name' => $parent->name,
            'parent_id' => $child->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_category_with_products_or_children_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->signIn($admin)->deleteJson("/api/v1/admin/categories/{$product->category_id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $this->signIn($admin)->deleteJson("/api/v1/admin/categories/{$parent->id}")->assertStatus(409);

        $empty = Category::factory()->create();
        $this->signIn($admin)->deleteJson("/api/v1/admin/categories/{$empty->id}")->assertOk();
        $this->assertSoftDeleted($empty);
    }

    public function test_brand_crud_and_delete_protection(): void
    {
        $admin = $this->admin();

        $brandId = $this->signIn($admin)->postJson('/api/v1/admin/brands', ['name' => 'Nimbus', 'is_active' => true])
            ->assertCreated()->json('data.id');

        $this->signIn($admin)->putJson("/api/v1/admin/brands/{$brandId}", ['name' => 'Nimbus Labs', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->signIn($admin)->postJson('/api/v1/admin/brands', ['name' => 'Nimbus Labs'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->product(attributes: ['brand_id' => $brandId]);
        $this->signIn($admin)->deleteJson("/api/v1/admin/brands/{$brandId}")->assertStatus(409);
    }

    public function test_inactive_brand_is_hidden_from_storefront(): void
    {
        $brand = Brand::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/brands/{$brand->slug}")->assertNotFound();
    }
}
