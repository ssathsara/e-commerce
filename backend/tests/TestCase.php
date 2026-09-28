<?php

namespace Tests;

use App\Enums\RoleName;
use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles and permissions are part of the application's configuration.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(RoleName $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }

    protected function customer(array $attributes = []): User
    {
        return $this->userWithRole(RoleName::Customer, $attributes);
    }

    protected function staff(): User
    {
        return $this->userWithRole(RoleName::Staff);
    }

    protected function admin(): User
    {
        return $this->userWithRole(RoleName::Admin);
    }

    protected function superAdmin(): User
    {
        return $this->userWithRole(RoleName::SuperAdmin);
    }

    /**
     * Authenticate the next request(s) as $user.
     *
     * Guards cache the resolved user (Sanctum's RequestGuard in particular), and one
     * application instance serves every request in a test, so cached guards are reset
     * first. In production each HTTP request gets a fresh application.
     */
    protected function signIn(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    /** Active product in an active category. Price in cents. */
    protected function product(int $price = 2000, int $stock = 10, array $attributes = []): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($price)
            ->stock($stock)
            ->create($attributes);
    }

    protected function variantProduct(array $variantStocks = [5, 5], int $price = 3000): Product
    {
        $product = $this->product($price, array_sum($variantStocks));

        foreach ($variantStocks as $index => $stock) {
            ProductVariant::factory()->for($product)->stock($stock)->create(['name' => "Option {$index}", 'sort_order' => $index]);
        }

        return $product->fresh();
    }

    protected function addressFor(User $user): Address
    {
        return Address::factory()->for($user)->create();
    }

    protected function addToCart(User $user, Product $product, int $quantity = 1, ?int $variantId = null): TestResponse
    {
        return $this->signIn($user)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    /** Places an order for whatever is in the user's cart. */
    protected function checkout(User $user, array $overrides = []): TestResponse
    {
        $address = $user->addresses()->first() ?? $this->addressFor($user);

        return $this->signIn($user)->postJson('/api/v1/orders', array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'shipping_address_id' => $address->id,
            'billing_same_as_shipping' => true,
            'shipping_method' => 'standard',
            'payment_method' => 'card',
            'payment_token' => 'tok_visa',
        ], $overrides));
    }
}
