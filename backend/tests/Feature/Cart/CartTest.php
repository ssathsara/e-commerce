<?php

namespace Tests\Feature\Cart;

use App\Enums\ProductStatus;
use App\Models\Coupon;
use Tests\TestCase;

class CartTest extends TestCase
{
    public function test_guest_gets_a_cart_token_and_can_reuse_it(): void
    {
        $product = $this->product(2000);

        $response = $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.item_count', 2);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $this->getJson('/api/v1/cart', ['X-Cart-Token' => $token])->assertOk()->assertJsonPath('data.item_count', 2);
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.item_count', 0);
    }

    public function test_cart_calculations_are_correct(): void
    {
        $customer = $this->customer();
        $a = $this->product(2550); // $25.50
        $b = $this->product(1000); // $10.00

        $this->addToCart($customer, $a, 2);
        $response = $this->addToCart($customer, $b, 3);

        // subtotal 81.00; below free-shipping threshold (100) => standard $5; tax 10% of 81 = 8.10
        $response->assertJsonPath('data.totals', [
            'subtotal' => 81,
            'discount' => 0,
            'shipping' => 5,
            'tax' => 8.1,
            'total' => 94.1,
        ]);

        // Express shipping is never free.
        $this->signIn($customer)->getJson('/api/v1/cart?shipping_method=express')->assertJsonPath('data.totals.shipping', 15);
    }

    public function test_free_standard_shipping_above_threshold(): void
    {
        $customer = $this->customer();

        $this->addToCart($customer, $this->product(12000))->assertJsonPath('data.totals.shipping', 0);
    }

    public function test_client_cannot_manipulate_prices(): void
    {
        $customer = $this->customer();
        $product = $this->product(5000);

        $this->signIn($customer)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 0.01,
            'unit_price' => 0.01,
            'total' => 0.01,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 50)
            ->assertJsonPath('data.totals.subtotal', 50);
    }

    public function test_quantity_cannot_exceed_stock(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000, 3);

        $this->addToCart($customer, $product, 4)->assertStatus(409)->assertJsonPath('success', false);
        $this->addToCart($customer, $product, 3)->assertCreated();
        // Adding more of the same line counts the existing quantity.
        $this->addToCart($customer, $product, 1)->assertStatus(409);
    }

    public function test_out_of_stock_and_unavailable_products_cannot_be_added(): void
    {
        $customer = $this->customer();

        $this->addToCart($customer, $this->product(1000, 0, ['status' => ProductStatus::OutOfStock]))->assertStatus(409);
        $this->addToCart($customer, $this->product(1000, 5, ['status' => ProductStatus::Draft]))->assertStatus(422);
        $this->signIn($customer)->postJson('/api/v1/cart/items', ['product_id' => 999999, 'quantity' => 1])->assertStatus(422);
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $customer = $this->customer();
        $product = $this->product();

        foreach ([0, -1, 'abc', 1000] as $quantity) {
            $this->signIn($customer)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
                ->assertStatus(422)->assertJsonValidationErrors('quantity');
        }
    }

    public function test_variant_is_required_for_products_with_variants(): void
    {
        $customer = $this->customer();
        $product = $this->variantProduct([2, 5]);
        $variant = $product->variants()->first();

        $this->addToCart($customer, $product)->assertStatus(422)->assertJsonValidationErrors('variant_id');
        $this->addToCart($customer, $product, 3, $variant->id)->assertStatus(409);
        $this->addToCart($customer, $product, 2, $variant->id)
            ->assertCreated()
            ->assertJsonPath('data.items.0.variant.id', $variant->id);
    }

    public function test_items_can_be_updated_removed_and_cleared(): void
    {
        $customer = $this->customer();
        $itemId = $this->addToCart($customer, $this->product(1000, 10), 1)->json('data.items.0.id');

        $this->signIn($customer)->putJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])->assertOk()->assertJsonPath('data.item_count', 4);
        $this->signIn($customer)->putJson("/api/v1/cart/items/{$itemId}", ['quantity' => 11])->assertStatus(409);
        $this->signIn($customer)->deleteJson("/api/v1/cart/items/{$itemId}")->assertOk()->assertJsonPath('data.item_count', 0);

        $this->addToCart($customer, $this->product());
        $this->signIn($customer)->deleteJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_cannot_modify_another_customers_cart_item(): void
    {
        $owner = $this->customer();
        $itemId = $this->addToCart($owner, $this->product())->json('data.items.0.id');

        $intruder = $this->customer();
        $this->addToCart($intruder, $this->product());

        $this->signIn($intruder)->putJson("/api/v1/cart/items/{$itemId}", ['quantity' => 2])->assertNotFound();
        $this->signIn($intruder)->deleteJson("/api/v1/cart/items/{$itemId}")->assertNotFound();
    }

    public function test_price_changes_are_reflected_and_stock_drops_are_flagged(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000, 5);
        $this->addToCart($customer, $product, 4);

        $product->forceFill(['price' => 1500, 'stock_quantity' => 2])->save();

        $this->signIn($customer)->getJson('/api/v1/cart')
            ->assertJsonPath('data.items.0.unit_price', 15)
            ->assertJsonPath('data.items.0.issue', 'Only 2 left in stock.')
            ->assertJsonPath('data.has_issues', true);
    }

    public function test_guest_cart_is_merged_on_login(): void
    {
        $product = $this->product(1000, 10);
        $token = $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])->json('data.token');

        $customer = $this->customer();
        $this->addToCart($customer, $product, 1);
        auth()->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'Password123!'], ['X-Cart-Token' => $token])->assertOk();

        $this->signIn($customer)->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 3);
        $this->assertDatabaseMissing('carts', ['token' => $token]);
    }

    public function test_valid_coupon_is_applied_by_the_server(): void
    {
        $customer = $this->customer();
        Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);
        $this->addToCart($customer, $this->product(5000));

        $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => 'save20'])
            ->assertOk()
            ->assertJsonPath('data.coupon.code', 'SAVE20')
            ->assertJsonPath('data.coupon.is_applied', true)
            // 50.00 - 10.00 discount = 40.00 (+5 shipping, +4.00 tax)
            ->assertJsonPath('data.totals.discount', 10)
            ->assertJsonPath('data.totals.total', 49);

        $this->signIn($customer)->deleteJson('/api/v1/cart/coupon')->assertOk()->assertJsonPath('data.coupon', null);
    }

    public function test_coupon_validation_rules(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product(3000));

        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'LATER', 'starts_at' => now()->addDay()]);
        Coupon::factory()->create(['code' => 'BIGSPEND', 'min_order_amount' => 10000]);

        foreach (['NOPE' => 'invalid', 'OLD' => 'expired', 'OFF' => 'not active', 'LATER' => 'not valid yet', 'BIGSPEND' => 'minimum order'] as $code => $fragment) {
            $response = $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => $code]);
            $response->assertStatus(422)->assertJsonValidationErrors('coupon_code');
            $this->assertStringContainsString($fragment, $response->json('message'));
        }
    }

    public function test_fixed_coupon_respects_maximum_and_never_exceeds_subtotal(): void
    {
        $coupon = Coupon::factory()->fixed(10000)->make();
        $this->assertSame(3000, $coupon->discountFor(3000));

        $capped = Coupon::factory()->percentage(50)->make(['max_discount_amount' => 1000]);
        $this->assertSame(1000, $capped->discountFor(10000));
    }
}
