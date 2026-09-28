<?php

namespace Tests\Feature\Checkout;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Events\OrderPlaced;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_checkout_creates_order_deducts_stock_and_clears_cart(): void
    {
        Event::fake([OrderPlaced::class]);
        $customer = $this->customer();
        $product = $this->product(2500, 10);
        $this->addToCart($customer, $product, 2);

        $response = $this->checkout($customer);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value) // card paid => auto-confirmed
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.subtotal', 50)
            ->assertJsonPath('data.shipping_total', 5)
            ->assertJsonPath('data.tax_total', 5)
            ->assertJsonPath('data.grand_total', 60)
            ->assertJsonPath('data.items.0.quantity', 2);

        $order = Order::first();
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(2, $product->fresh()->sales_count);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => InventoryMovementType::Sale->value,
            'quantity' => -2,
            'stock_after' => 8,
        ]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid', 'amount' => 6000, 'gateway' => 'mock']);
        $this->signIn($customer)->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        Event::assertDispatched(OrderPlaced::class);
    }

    public function test_repeated_request_with_same_idempotency_key_does_not_duplicate_order(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000, 10);
        $this->addToCart($customer, $product, 1);
        $key = (string) Str::uuid();

        $first = $this->checkout($customer, ['idempotency_key' => $key])->assertCreated();
        $second = $this->checkout($customer, ['idempotency_key' => $key])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(1, \App\Models\Payment::count());
    }

    public function test_idempotency_keys_are_scoped_per_customer(): void
    {
        $key = (string) Str::uuid();

        foreach ([$this->customer(), $this->customer()] as $customer) {
            $this->addToCart($customer, $this->product());
            $this->checkout($customer, ['idempotency_key' => $key])->assertCreated();
        }

        $this->assertSame(2, Order::count());
    }

    public function test_out_of_stock_products_cannot_be_ordered(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000, 2);
        $this->addToCart($customer, $product, 2);

        // Someone else bought the stock after it was added to the cart.
        $product->forceFill(['stock_quantity' => 1])->save();

        $this->checkout($customer)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['cart']]);

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_last_unit_cannot_be_sold_twice_and_stock_never_goes_negative(): void
    {
        $product = $this->product(1000, 1);
        $first = $this->customer();
        $second = $this->customer();
        $this->addToCart($first, $product);
        $this->addToCart($second, $product);

        $this->checkout($first)->assertCreated();
        $this->checkout($second)->assertStatus(409);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(ProductStatus::OutOfStock, $product->fresh()->status);
        $this->assertSame(1, Order::count());
    }

    public function test_order_uses_current_catalogue_price_not_cart_time_price(): void
    {
        $customer = $this->customer();
        $product = $this->product(1000);
        $this->addToCart($customer, $product);
        $product->forceFill(['price' => 1200])->save();

        $this->checkout($customer)->assertCreated()->assertJsonPath('data.items.0.unit_price', 12);
    }

    public function test_empty_cart_cannot_be_checked_out(): void
    {
        $this->checkout($this->customer())->assertStatus(422)->assertJsonPath('message', 'Your cart is empty.');
    }

    public function test_checkout_rejects_another_customers_address_and_invalid_input(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product());
        $foreignAddress = $this->addressFor($this->customer());

        $this->checkout($customer, ['shipping_address_id' => $foreignAddress->id])
            ->assertStatus(422)->assertJsonValidationErrors('shipping_address_id');

        $this->checkout($customer, ['shipping_method' => 'teleport', 'payment_method' => 'bitcoin', 'idempotency_key' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['shipping_method', 'payment_method', 'idempotency_key']);

        $this->checkout($customer, ['payment_token' => null])->assertStatus(422)->assertJsonValidationErrors('payment_token');
    }

    public function test_declined_card_leaves_order_pending_and_payment_can_be_retried(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product(1000, 5));

        $response = $this->checkout($customer, ['payment_token' => 'tok_chargeDeclined'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'failed')
            ->assertJsonPath('payment_error', 'Your card was declined.');

        $orderId = $response->json('data.id');

        $this->signIn($customer)->postJson("/api/v1/orders/{$orderId}/payments", [
            'payment_token' => 'tok_visa',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'confirmed');

        // Paying again is refused.
        $this->signIn($customer)->postJson("/api/v1/orders/{$orderId}/payments", [
            'payment_token' => 'tok_visa',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->assertSame(1, Order::find($orderId)->payments()->where('status', 'paid')->count());
    }

    public function test_cash_on_delivery_order_stays_pending_payment(): void
    {
        $customer = $this->customer();
        $this->addToCart($customer, $this->product());

        $this->checkout($customer, ['payment_method' => 'cash_on_delivery', 'payment_token' => null])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_coupon_usage_is_recorded_and_limits_enforced(): void
    {
        Coupon::factory()->percentage(10)->create(['code' => 'ONCE', 'usage_limit_per_user' => 1]);
        $customer = $this->customer();

        $this->addToCart($customer, $this->product(5000));
        $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => 'ONCE'])->assertOk();
        $this->checkout($customer)->assertCreated()->assertJsonPath('data.discount_total', 5)->assertJsonPath('data.coupon_code', 'ONCE');

        $this->assertDatabaseHas('coupon_usages', ['user_id' => $customer->id, 'discount_amount' => 500]);

        $this->addToCart($customer, $this->product(5000));
        $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => 'ONCE'])->assertStatus(422);
    }

    public function test_global_coupon_usage_limit_is_enforced_at_checkout(): void
    {
        Coupon::factory()->percentage(10)->create(['code' => 'ONLYONE', 'usage_limit' => 1]);
        [$a, $b] = [$this->customer(), $this->customer()];

        foreach ([$a, $b] as $customer) {
            $this->addToCart($customer, $this->product(5000));
            $this->signIn($customer)->postJson('/api/v1/cart/coupon', ['coupon_code' => 'ONLYONE'])->assertOk();
        }

        $this->checkout($a)->assertCreated();
        $this->checkout($b)->assertStatus(422)->assertJsonValidationErrors('coupon_code');
    }

    public function test_order_snapshots_address_and_product_details(): void
    {
        $customer = $this->customer();
        $address = $this->addressFor($customer);
        $originalCity = $address->city;
        $product = $this->product(1000, 5, ['name' => 'Original Name']);
        $this->addToCart($customer, $product);
        $orderId = $this->checkout($customer, ['shipping_address_id' => $address->id])->json('data.id');

        $address->update(['city' => 'Changed City']);
        $product->update(['name' => 'Renamed']);

        $this->signIn($customer)->getJson("/api/v1/orders/{$orderId}")
            ->assertJsonPath('data.items.0.product_name', 'Original Name')
            ->assertJsonPath('data.shipping_address.city', $originalCity);
    }
}
